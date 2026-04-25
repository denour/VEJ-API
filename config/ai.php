<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Providers
    |--------------------------------------------------------------------------
    |
    | Configure which AI providers to use for text and image generation.
    | You can easily swap providers by changing these values.
    |
    */

    'text_provider' => env('AI_TEXT_PROVIDER', 'openai'),
    'image_provider' => env('AI_IMAGE_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Text Generation Providers
    |--------------------------------------------------------------------------
    |
    | Configuration for text generation providers.
    |
    */

    'text' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-5-nano'),
        ],
        // Add more text providers here
        // 'anthropic' => [
        //     'api_key' => env('ANTHROPIC_API_KEY'),
        //     'model' => env('ANTHROPIC_MODEL', 'claude-3-opus'),
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Generation Providers
    |--------------------------------------------------------------------------
    |
    | Configuration for image generation providers.
    |
    */

    'image' => [
        'banana' => [
            'api_key' => env('BANANA_API_KEY'),
            'model' => env('BANANA_MODEL', 'nano-banana'),
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
        ],
        'mock' => [
            'task_id' => env('MOCK_BANANA_TASK_ID', '6cf7ba74ee3e25a08b161392444af4222345'),
            'image_url' => env('MOCK_BANANA_IMAGE_URL', '["https://tempfile.aiquickdraw.com/h/6cf7ba74ee3e25a08b161392444af422_1766822849.png"]'),
        ],
    ],
];
