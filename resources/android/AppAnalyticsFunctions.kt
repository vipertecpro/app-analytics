package com.vipertecpro.plugins.app_analytics

// =============================================================================
// Analytics & Remote Config — Android native side
// =============================================================================
//
// Thin, defensive bridge over the Firebase Analytics and Remote Config SDKs
// (the only third-party code). Firebase is initialised on first use from the
// google-services.json the plugin copies into assets/vipertecpro/ — without
// the Google Services Gradle plugin, so an app with no config file still
// builds and runs: every call is skipped, Status explains why, and a Remote
// Config fetch reports "not_configured" so PHP keeps serving its defaults.
// If another plugin already initialised the default FirebaseApp, that one is
// used as it is.
// =============================================================================

import android.content.Context
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.util.Log
import androidx.fragment.app.FragmentActivity
import com.google.android.gms.tasks.Tasks
import com.google.firebase.FirebaseApp
import com.google.firebase.FirebaseOptions
import com.google.firebase.analytics.FirebaseAnalytics
import com.google.firebase.remoteconfig.FirebaseRemoteConfig
import com.google.firebase.remoteconfig.FirebaseRemoteConfigClientException
import com.google.firebase.remoteconfig.FirebaseRemoteConfigFetchThrottledException
import com.google.firebase.remoteconfig.FirebaseRemoteConfigSettings
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.utils.NativeActionCoordinator
import org.json.JSONObject
import java.io.IOException
import java.util.concurrent.TimeUnit

object AppAnalyticsFunctions {

    private const val TAG = "AppAnalytics"
    private const val EVENT_FETCHED = "Vipertecpro\\AppAnalytics\\Events\\RemoteConfigFetched"
    private const val EVENT_FAILED = "Vipertecpro\\AppAnalytics\\Events\\RemoteConfigFetchFailed"
    private const val PREFS = "vipertecpro_analytics"

    // ------------------------------------------------------------ Setup

    @Volatile private var app: FirebaseApp? = null
    @Volatile private var reason: String? = null
    @Volatile private var attempted = false

    /** The configured FirebaseApp, initialising it from assets the first time; null when not configured. */
    @Synchronized
    internal fun firebase(context: Context): FirebaseApp? {
        if (attempted) return app
        attempted = true

        FirebaseApp.getApps(context).firstOrNull { it.name == FirebaseApp.DEFAULT_APP_NAME }?.let {
            app = it
            return it
        }

        val json = try {
            context.assets.open("vipertecpro/google-services.json").bufferedReader().use { it.readText() }
        } catch (_: IOException) {
            reason = "no_config_file"
            return null
        }

        val options = optionsFrom(json, context.packageName)
        if (options == null) {
            reason = "invalid_config"
            return null
        }

        app = try {
            FirebaseApp.initializeApp(context.applicationContext, options)
        } catch (e: Exception) {
            Log.e(TAG, "Firebase init failed: ${e.message}", e)
            reason = "invalid_config"
            null
        }
        return app
    }

    /** google-services.json → FirebaseOptions for this package's client entry. */
    private fun optionsFrom(json: String, packageName: String): FirebaseOptions? = try {
        val root = JSONObject(json)
        val project = root.getJSONObject("project_info")
        val clients = root.getJSONArray("client")
        val client = (0 until clients.length()).map { clients.getJSONObject(it) }.firstOrNull {
            it.optJSONObject("client_info")?.optJSONObject("android_client_info")?.optString("package_name") == packageName
        } ?: return null

        val appId = client.getJSONObject("client_info").getString("mobilesdk_app_id")
        val apiKey = client.getJSONArray("api_key").getJSONObject(0).getString("current_key")

        FirebaseOptions.Builder()
            .setApplicationId(appId)
            .setApiKey(apiKey)
            .setProjectId(project.optString("project_id").ifBlank { null })
            .setGcmSenderId(project.optString("project_number").ifBlank { null })
            .setStorageBucket(project.optString("storage_bucket").ifBlank { null })
            .build()
    } catch (e: Exception) {
        Log.e(TAG, "google-services.json could not be read: ${e.message}")
        null
    }

    private fun analytics(context: Context): FirebaseAnalytics? =
        firebase(context)?.let { FirebaseAnalytics.getInstance(context) }

    // ------------------------------------------------------------ Status

    class Status(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val firebase = firebase(activity)
            val result = mutableMapOf<String, Any>(
                "configured" to (firebase != null),
                "collectionEnabled" to (firebase != null && collectionEnabled(activity)),
                "platform" to "android",
            )
            reason?.let { result["reason"] = it }
            if (firebase != null) {
                firebase.options.projectId?.let { result["projectId"] = it }
                // appInstanceId is a Task; wait briefly unless we are on the main thread.
                if (Looper.myLooper() != Looper.getMainLooper()) {
                    try {
                        Tasks.await(FirebaseAnalytics.getInstance(activity).appInstanceId, 2, TimeUnit.SECONDS)
                            ?.let { result["appInstanceId"] = it }
                    } catch (_: Exception) {
                        // No id yet (e.g. collection off) — leave it out.
                    }
                }
            }
            return result
        }
    }

    // ------------------------------------------------------------ Analytics

    class LogEvent(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val analytics = analytics(activity) ?: return mapOf("logged" to false)
            val name = parameters["name"] as? String ?: return mapOf("logged" to false)
            val bundle = Bundle()
            (parameters["parameters"] as? JSONObject)?.let { params ->
                params.keys().forEach { key ->
                    when (val value = params.get(key)) {
                        is String -> bundle.putString(key, value)
                        is Int -> bundle.putLong(key, value.toLong())
                        is Long -> bundle.putLong(key, value)
                        is Double -> bundle.putDouble(key, value)
                        is Number -> bundle.putDouble(key, value.toDouble())
                    }
                }
            }
            analytics.logEvent(name, if (bundle.isEmpty) null else bundle)
            return mapOf("logged" to true)
        }
    }

    class SetUserProperty(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val name = parameters["name"] as? String ?: return emptyMap()
            analytics(activity)?.setUserProperty(name, parameters["value"] as? String)
            return emptyMap()
        }
    }

    class SetUserId(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            analytics(activity)?.setUserId(parameters["id"] as? String)
            return emptyMap()
        }
    }

    class SetCollectionEnabled(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val enabled = parameters["enabled"] as? Boolean ?: true
            activity.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putBoolean("collection", enabled).apply()
            analytics(activity)?.setAnalyticsCollectionEnabled(enabled)
            return emptyMap()
        }
    }

    class SetConsent(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val analytics = analytics(activity) ?: return emptyMap()
            fun status(key: String) =
                if (parameters[key] as? Boolean == true) FirebaseAnalytics.ConsentStatus.GRANTED else FirebaseAnalytics.ConsentStatus.DENIED
            analytics.setConsent(
                mapOf(
                    FirebaseAnalytics.ConsentType.ANALYTICS_STORAGE to status("analyticsStorage"),
                    FirebaseAnalytics.ConsentType.AD_STORAGE to status("adStorage"),
                    FirebaseAnalytics.ConsentType.AD_USER_DATA to status("adUserData"),
                    FirebaseAnalytics.ConsentType.AD_PERSONALIZATION to status("adPersonalization"),
                )
            )
            return emptyMap()
        }
    }

    class ResetData(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            analytics(activity)?.resetAnalyticsData()
            return emptyMap()
        }
    }

    // ------------------------------------------------------------ Remote Config

    class FetchAndActivate(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val firebase = firebase(activity)
            if (firebase == null) {
                val message = if (reason == "invalid_config") "google-services.json could not be read." else "No google-services.json in the app; serving defaults."
                Handler(Looper.getMainLooper()).post { failed(activity, "not_configured", message) }
                return emptyMap()
            }

            val interval = (parameters["minimumFetchInterval"] as? Number)?.toLong()?.coerceAtLeast(0) ?: 3600L
            val config = FirebaseRemoteConfig.getInstance(firebase)
            config.setConfigSettingsAsync(
                FirebaseRemoteConfigSettings.Builder().setMinimumFetchIntervalInSeconds(interval).build()
            ).continueWithTask { config.fetchAndActivate() }
                .addOnSuccessListener { activated ->
                    val keys = config.all.count { it.value.source == FirebaseRemoteConfig.VALUE_SOURCE_REMOTE }
                    dispatch(activity, EVENT_FETCHED, JSONObject().apply {
                        put("activated", activated == true)
                        put("keys", keys)
                    })
                }
                .addOnFailureListener { e ->
                    val reason = when (e) {
                        is FirebaseRemoteConfigFetchThrottledException -> "throttled"
                        is FirebaseRemoteConfigClientException -> if (e.cause is IOException) "network" else "error"
                        else -> "error"
                    }
                    failed(activity, reason, e.message)
                }
            return emptyMap()
        }
    }

    class GetValues(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val firebase = firebase(activity) ?: return mapOf("values" to JSONObject())
            val values = JSONObject()
            FirebaseRemoteConfig.getInstance(firebase).all.forEach { (key, value) ->
                if (value.source == FirebaseRemoteConfig.VALUE_SOURCE_REMOTE) values.put(key, value.asString())
            }
            return mapOf("values" to values)
        }
    }

    // ------------------------------------------------------------ Helpers

    private fun collectionEnabled(context: Context): Boolean =
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getBoolean("collection", true)

    private fun failed(activity: FragmentActivity, reason: String, message: String?) {
        dispatch(activity, EVENT_FAILED, JSONObject().apply {
            put("reason", reason)
            message?.takeIf { it.isNotBlank() }?.let { put("message", it) }
        })
    }

    private fun dispatch(activity: FragmentActivity, event: String, payload: JSONObject) {
        NativeActionCoordinator.dispatchEvent(activity, event, payload.toString())
    }
}
