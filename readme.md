# Laravel Serializer

Binds the JMS serializer library to Laravel and allows schemas to
be defined according to normal Laravel configuration conventions.

## Setup

To get Laravel Serializer ready for use in your project, take the usual steps for setting up a Laravel package.

 1. Run: ```composer require psychob/laravel-serializer```
 2. Add to your service provider: ```Atrauzzi\LaravelSerializer\JMSSerializerServiceProvider::class```
 3. Run ```php artisan vendor:publish```

```
Note: Because this package does not perform any data storage, no migrations are required.
```

## Configuring

All configuration is stored in `config/serializer.php`.

```php
<?php
    return [
        // Auto fetch properties which are:
        //  * public - public properties (which can be accessed with getter)
        //  * static - static properties (which can be accessed with getter)
        // If default visibility is not set, or it's empty, only attributes
        // list will be used. If it's set, attributes will be considered
        // first, and then properties with default visibility will be
        // added
        'default_visibility' => [],

        // Add to serializer output _type property with class name
        'meta_property' => false,

        'mappings' => [
            Type::class => [
                'default_visibility' => [],
                'meta_property' => false,

                // You can map attributes in 3 ways:
                //  * listing them by name
                //  * listing them by name, and forcing correct
                //    serialization type
                //  * listring them by name and setting other options:
                //     * name - serialized name
                //     * type - serialization type
                //     * groups - list of groups
                'attributes' => [
                    'simple_property',
                    'typed_property' => 'string',
                    'complex_property' => [
                        'type' => 'string',
                        'name' => 'custom_name',
                        'groups' => ['group'],
                    ],
                ],
            ]
        ],
    ];
```

### Credits

This library is maintained by [Andrzej Budzanowski](https://andrzej.budzanowski.pl/),
based on [atrauzzi/laravel-serializer](https://github.com/atrauzzi/laravel-serializer)
by [Alexander Trauzzi](http://goo.gl/Bq49Bg)
