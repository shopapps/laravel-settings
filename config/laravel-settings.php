<?php

return [

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
        'LaravelSettingsResource' => \Shopapps\LaravelSettings\Resources\SettingsResource::class,
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
        'settings_navigation' => 'heroicon-o-lock-closed',
    ],

    /*
     *  Navigation items order - int value, false  restores the default position
     */

    'sort' => [
        'settings_navigation' => false,
    ],


];
