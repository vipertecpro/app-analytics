import Foundation
import FirebaseCore
import FirebaseAnalytics
import FirebaseRemoteConfig

// =============================================================================
// Analytics & Remote Config — iOS native side
// =============================================================================
//
// Thin, defensive bridge over the Firebase Analytics and Remote Config SDKs
// (the only third-party code). Firebase is configured at launch from
// GoogleService-Info.plist when the app has one; when it does not, every call
// is skipped instead of crashing, Status explains why, and a Remote Config
// fetch reports "not_configured" so PHP keeps serving its defaults.
// =============================================================================

/// Called once at launch through the manifest's ios.init_function.
func initAppAnalytics() {
    FirebaseSetup.configureIfPossible()
}

enum FirebaseSetup {
    private(set) static var configured = false
    private(set) static var reason: String?

    static func configureIfPossible() {
        if FirebaseApp.app() != nil {
            configured = true
            reason = nil
            return
        }

        guard let path = Bundle.main.path(forResource: "GoogleService-Info", ofType: "plist") else {
            reason = "no_config_file"
            return
        }

        guard let options = FirebaseOptions(contentsOfFile: path), !options.googleAppID.isEmpty else {
            reason = "invalid_config"
            return
        }

        FirebaseApp.configure(options: options)
        configured = FirebaseApp.app() != nil
        reason = configured ? nil : "invalid_config"
    }

    /// The collection switch Firebase does not let us read back.
    static var collectionEnabled: Bool {
        get { UserDefaults.standard.object(forKey: "vipertecpro.analytics.collection") as? Bool ?? true }
        set { UserDefaults.standard.set(newValue, forKey: "vipertecpro.analytics.collection") }
    }
}

enum AppAnalyticsFunctions {

    // MARK: Status

    class Status: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            var result: [String: Any] = [
                "configured": FirebaseSetup.configured,
                "collectionEnabled": FirebaseSetup.configured && FirebaseSetup.collectionEnabled,
                "platform": "ios",
            ]
            if let reason = FirebaseSetup.reason { result["reason"] = reason }
            if FirebaseSetup.configured {
                if let id = Analytics.appInstanceID() { result["appInstanceId"] = id }
                if let project = FirebaseApp.app()?.options.projectID { result["projectId"] = project }
            }
            return result
        }
    }

    // MARK: Analytics

    class LogEvent: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard FirebaseSetup.configured, let name = parameters["name"] as? String else { return ["logged": false] }
            let raw = parameters["parameters"] as? [String: Any] ?? [:]
            var clean: [String: Any] = [:]
            for (key, value) in raw {
                switch value {
                case let number as NSNumber: clean[key] = number
                case let string as String: clean[key] = string
                default: continue
                }
            }
            Analytics.logEvent(name, parameters: clean.isEmpty ? nil : clean)
            return ["logged": true]
        }
    }

    class SetUserProperty: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard FirebaseSetup.configured, let name = parameters["name"] as? String else { return [:] }
            Analytics.setUserProperty(parameters["value"] as? String, forName: name)
            return [:]
        }
    }

    class SetUserId: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard FirebaseSetup.configured else { return [:] }
            Analytics.setUserID(parameters["id"] as? String)
            return [:]
        }
    }

    class SetCollectionEnabled: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let enabled = parameters["enabled"] as? Bool ?? true
            FirebaseSetup.collectionEnabled = enabled
            if FirebaseSetup.configured { Analytics.setAnalyticsCollectionEnabled(enabled) }
            return [:]
        }
    }

    class SetConsent: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard FirebaseSetup.configured else { return [:] }
            func status(_ key: String) -> ConsentStatus { (parameters[key] as? Bool ?? false) ? .granted : .denied }
            Analytics.setConsent([
                .analyticsStorage: status("analyticsStorage"),
                .adStorage: status("adStorage"),
                .adUserData: status("adUserData"),
                .adPersonalization: status("adPersonalization"),
            ])
            return [:]
        }
    }

    class ResetData: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            if FirebaseSetup.configured { Analytics.resetAnalyticsData() }
            return [:]
        }
    }

    // MARK: Remote Config

    class FetchAndActivate: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard FirebaseSetup.configured else {
                RemoteConfigEvents.failed("not_configured", FirebaseSetup.reason == "invalid_config"
                    ? "GoogleService-Info.plist could not be read."
                    : "No GoogleService-Info.plist in the app; serving defaults.")
                return [:]
            }

            let interval = (parameters["minimumFetchInterval"] as? NSNumber)?.doubleValue ?? 3600
            let config = RemoteConfig.remoteConfig()
            let settings = RemoteConfigSettings()
            settings.minimumFetchInterval = max(0, interval)
            config.configSettings = settings

            config.fetchAndActivate { status, error in
                switch status {
                case .successFetchedFromRemote:
                    RemoteConfigEvents.fetched(activated: true, keys: config.allKeys(from: .remote).count)
                case .successUsingPreFetchedData:
                    RemoteConfigEvents.fetched(activated: false, keys: config.allKeys(from: .remote).count)
                case .error:
                    let nsError = error as NSError?
                    let throttled = nsError?.domain == RemoteConfigErrorDomain
                        && nsError?.code == RemoteConfigError.throttled.rawValue
                    let network = nsError?.domain == NSURLErrorDomain
                    RemoteConfigEvents.failed(throttled ? "throttled" : (network ? "network" : "error"), error?.localizedDescription)
                @unknown default:
                    RemoteConfigEvents.failed("error", error?.localizedDescription)
                }
            }
            return [:]
        }
    }

    class GetValues: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard FirebaseSetup.configured else { return ["values": [String: String]()] }
            let config = RemoteConfig.remoteConfig()
            var values: [String: String] = [:]
            for key in config.allKeys(from: .remote) {
                values[key] = config.configValue(forKey: key, source: .remote).stringValue
            }
            return ["values": values]
        }
    }
}

enum RemoteConfigEvents {
    static func fetched(activated: Bool, keys: Int) {
        send("Vipertecpro\\AppAnalytics\\Events\\RemoteConfigFetched", ["activated": activated, "keys": keys])
    }

    static func failed(_ reason: String, _ message: String?) {
        var payload: [String: Any] = ["reason": reason]
        if let message, !message.isEmpty { payload["message"] = message }
        send("Vipertecpro\\AppAnalytics\\Events\\RemoteConfigFetchFailed", payload)
    }

    private static func send(_ event: String, _ payload: [String: Any]) {
        let deliver: () -> Void = { LaravelBridge.shared.send?(event, payload) }
        if Thread.isMainThread { deliver() } else { DispatchQueue.main.async(execute: deliver) }
    }
}
