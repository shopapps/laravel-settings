<?php
/*
 * Config for shopapps/laravel-settings
 */
return [

    /*
     * Set edit mode to 'text' or 'simple'
     * simple - uses Filament Key Values when editing arrays
     * text - uses Textarea when editing complex arrays
     *
     */
    'edit_mode' => env('LARAVEL_SETTINGS_EDIT_MODE', 'text'),

    'access_control' => [
        'enabled' => env('LARAVEL_SETTINGS_ACCESS_CONTROL_ENABLED', false), // set to true to restrict access to the Resource
        'spatie' => [
            'enabled' => env('LARAVEL_SETTINGS_SPATIE_PERMISSIONS_ACTIVE', false),
            'permission' => env('LARAVEL_SETTINGS_SPATIE_PERMISSION', 'laravel_settings.view'),
        ],
        'allowed' => [
            'emails' => array_map('trim', explode(',', env('LARAVEL_SETTINGS_ALLOWED_EMAILS', '')) ?? []), // string of comma-delimited emails e.g. 'user1@test.com,user2@test.com'
            'user_ids' => array_map('trim', explode(',', env('LARAVEL_SETTINGS_ALLOWED_USER_IDS', '')) ?? []), // string of comma delimited user ids e.g. '1,2,3'
        ],
    ],
    
    'table_name' => 'settings',
    'table' => [
        'name' => 'settings',
        'primary_key' => 'id',
        'columns' => [
            'id' => 'integer',
            'key' => 'string',
            'value' => 'text',
            'tenant_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ],
    ],
    'resources' => [
        'LaravelSettingsResource' => \Shopapps\LaravelSettings\Resources\LaravelSettingsResource::class,
    ],
    'models' => [
        'laravel-setting' => \Shopapps\LaravelSettings\Models\LaravelSetting::class,
    ],

    'cache_settings' => true,

    'cache_ttl' => 86400,

    'navigation_section_group' => 'Settings', // Default uses language constant

    'scope_to_tenant' => true,

    /*
     * Set as false to remove from navigation.
     */
    'should_register_on_navigation' => [
        'settings' => true,
    ],


    /*
     * Set as true to use simple modal resource.
     */
    'should_use_simple_modal_resource' => [
        'settings' => true,
    ],

    /**
     * Set to true to redirect to the resource index instead of the view
     */
    'should_redirect_to_index' => [
        'settings' => [
            'after_create' => false,
            'after_edit' => false
        ],
    ],

    /**
     * Set to true to display relation managers in the resources
     */
    'should_display_relation_managers' => [
        'settings' => true,
    ],

    /*
     * Icons to use for navigation
     */
    'icons' => [
        'settings_navigation' => 'heroicon-o-queue-list',
    ],

    /*
     *  Navigation items order - int value, false  restores the default position
     */

    'sort' => [
        'settings_navigation' => false,
    ],


];
