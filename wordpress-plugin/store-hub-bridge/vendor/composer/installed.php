<?php return array(
    'root' => array(
        'name' => 'digitalogies/stripe-payment',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => '96f5497d9ba94c8d14a6d85a6a34aa119182d9e2',
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'digitalogies/stripe-payment' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '96f5497d9ba94c8d14a6d85a6a34aa119182d9e2',
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'stripe/stripe-php' => array(
            'pretty_version' => 'v16.6.0',
            'version' => '16.6.0.0',
            'reference' => 'd6de0a536f00b5c5c74f36b8f4d0d93b035499ff',
            'type' => 'library',
            'install_path' => __DIR__ . '/../stripe/stripe-php',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
