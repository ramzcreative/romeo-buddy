<?php

return [
    'palettes' => [
        'Background' => [
            [
                'label' => 'None',
				'default' => true,
                'color' => [
                    [
                        'color' => 'transparent',
                        'background' => 'bg--none'
                    ]
                ]
            ],
            [
                'label' => 'Primary',
				'default' => false,
                'color' => [
                    [
                        'color' => '#00aeef',
                        'background' => 'bg--color1'
                    ]
                ]
            ],
            [
				'label' => 'Secondary',
				'default' => false,
				'color' => [
					[
						'color' => '#ff6b4a',
						'background' => 'bg--color2'
					]
				]
            ],
            [
				'label' => 'Calm',
				'default' => false,
				'color' => [
					[
						'color' => '#f7ddb2',
						'background' => 'bg--color3'
					]
				]
            ]
        ]
    ]
];
