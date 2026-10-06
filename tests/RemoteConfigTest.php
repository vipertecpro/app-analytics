<?php

use Vipertecpro\AppAnalytics\RemoteConfig;

beforeEach(function () {
    $this->config = new RemoteConfig(defaults: [
        'welcome_title' => 'Hello',
        'new_checkout' => false,
        'max_items' => 20,
        'discount_rate' => 0.1,
        'beta_flag' => 'yes',
        'layout' => ['columns' => 2],
    ]);
});

it('serves typed defaults when nothing was fetched', function () {
    expect($this->config->string('welcome_title'))->toBe('Hello')
        ->and($this->config->bool('new_checkout', true))->toBeFalse()
        ->and($this->config->bool('beta_flag'))->toBeTrue()
        ->and($this->config->int('max_items'))->toBe(20)
        ->and($this->config->float('discount_rate'))->toBe(0.1)
        ->and($this->config->json('layout'))->toBe(['columns' => 2])
        ->and($this->config->source('max_items'))->toBe('default');
});

it('falls back to the call-site default for unknown keys', function () {
    expect($this->config->string('missing'))->toBeNull()
        ->and($this->config->string('missing', 'x'))->toBe('x')
        ->and($this->config->bool('missing', true))->toBeTrue()
        ->and($this->config->int('missing', 7))->toBe(7)
        ->and($this->config->source('missing'))->toBe('static');
});

it('lets remote values override defaults key by key', function () {
    (fn () => $this->remote = ['new_checkout' => 'true', 'max_items' => '50', 'layout' => '{"columns":3}', 'extra' => 'on'])->call($this->config);

    expect($this->config->bool('new_checkout'))->toBeTrue()
        ->and($this->config->int('max_items'))->toBe(50)
        ->and($this->config->json('layout'))->toBe(['columns' => 3])
        ->and($this->config->string('welcome_title'))->toBe('Hello')
        ->and($this->config->source('new_checkout'))->toBe('remote')
        ->and(array_keys($this->config->all()))->toBe(['beta_flag', 'discount_rate', 'extra', 'layout', 'max_items', 'new_checkout', 'welcome_title'])
        ->and($this->config->all()['extra'])->toBe(['value' => 'on', 'source' => 'remote']);

    $this->config->refresh();

    expect($this->config->int('max_items'))->toBe(20);
});

it('merges extra defaults and validates keys and intervals', function () {
    $this->config->setDefaults(['promo' => 'autumn']);

    expect($this->config->string('promo'))->toBe('autumn')
        ->and(fn () => $this->config->setDefaults(['bad-key' => 1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->config->string('has space'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->config->fetchAndActivate(-1))->toThrow(InvalidArgumentException::class, 'cannot be negative');

    $this->config->fetchAndActivate(0);
});
