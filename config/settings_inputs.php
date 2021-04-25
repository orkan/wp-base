<?php
/*
 * This file is part of the orkan/wp-base package.
 * Copyright (c) 2024 Orkan <orkans+wpbase@gmail.com>
 */
use Orkan\Inputs;

/**
 * Settings: fields.
 */
return [
	// -----------------------------------------------------------------------------------------------------------------
	// Section A
	'number_a1' => [
		'type'     => 'number',
		'title'    => 'Number #1',
		'section'  => 'section_a',
		'order'    => 110,
		'premium'  => true,
	],
	'number_a2' => [
		'type'     => 'number',
		'title'    => 'Number #2',
		'section'  => 'section_a',
		'desc'     => 'Type number of something.',
		'order'    => 120,
		'defval'   => '4',
	],
	'date_a3' => [
		'type'     => 'date',
		'title'    => 'Expiry date',
		'section'  => 'section_a',
		'order'    => 130,
		'min'      => '2023-04-1',
		'max'      => '2025-04-30',
		'defval'   => '2024-04-15',
	],
	// -----------------------------------------------------------------------------------------------------------------
	// Section B
	'text_b1'  => [
		'type'     => 'text',
		'title'    => 'Text #1',
		'desc'     => "Type some text.",
		'section'  => 'section_b',
		'order'    => 210,
	],
	'group_b1' => [
		'type'     => 'group',
		'title'    => 'Groupped',
		'desc'     => <<<EOT
		You can group inputs by nesting inputs or other groups of inputs.
		The nesting level is theoretically unlimited, however the inputs will be rendered in flat manner.
		To make them look hierarchical use Inputs::buildTableNested() method.
		EOT,
		'section'  => 'section_b',
		'order'    => 220,
		'items'    => [
			'group_b1_switch'  => [
				'type'   => 'switch',
				'tag'    => 'Switch me OFF',
				'defval' => 'on',
			],
			'group_b1_nested' => [
				'type'   => 'group',
				'render' => [ Inputs::class, 'buildTableNested' ],
			],
			'group_b1_text'  => [
				'type' => 'text',
				'hint' => 'Im inside [Groupped]',
			],
		],
	],
];
