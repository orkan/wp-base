<?php
/*
 * This file is part of the orkan/wp-base package.
 * Copyright (c) 2024 Orkan <orkans+wpbase@gmail.com>
 */
use Orkan\Inputs;

/**
 * Settings tool: Formix.
 */
return [
	'info' => [
		'type'   => 'html',
		'title'  => 'Info',
		'desc'   => 'Inputs defined in: ' . __FILE__,
		'html'   => <<<HTML
		Part of FORM inputs on this page is copied form Settings page and initially feeded with data from DB.
		However any changes are recorded locally only, and have no effect on Settings page.
		The data is stored in browser cookies instead.
		You can review what cookies are saved on this page <a href="options-general.php?page=ork-settings&action=config#cookie">here</a>
		<hr>
		Most of the inputs here will keep the history of entered values. To reset these values use [Reset form] checkbox below.
		<hr>
		HTML,
	],
	'attachment' => [
		'type'   => 'group',
		'title'  => 'Attachment ID',
		'order'  => 10,
		'items'  => [
			'id' => [
				'type'    => 'number',
				'reset'   => false,
				'history' => 8,
				'tag'     => ' No reset! ',
			],
		],
	],
	'notice' => [
		'type'   => 'text',
		'title'  => 'Admin notice',
		'desc'   => 'Use this field to display admin notice',
		'order'  => 20,
	],
	'settings' => [
		'type'   => 'group',
		'kind'   => 'toggle',
		'title'  => 'Plugin > Settings',
		'labels' => 'Modify',
		'defval' => 'off',
		'order'  => 30,
		'items'  => [
			'table' => [
				'type'   => 'group',
				'class'  => 'form-table form-table-settings',
				'style'  => 'border: 1px gray dashed;',
				'render' => [ Inputs::class, 'buildTableNested' ],
				'items'  => [
					'info' => [
						'type'   => 'html',
						'title'  => 'Info',
						'html'   => 'This [table] is a mix of current inputs and inputs defined in: ' . __DIR__. '/settings_inputs.php',
						'order'  => 10,
					],
					'premium' => [
						'type'   => 'checkbox',
						'title'  => 'User capability',
						'tag'    => 'Is Premium',
						'defval' => 'off',
						'nodef'  => true,
						'order'  => 20,
					],
					'notes' => [
						'type'   => 'html',
						'title'  => 'Notes',
						'desc'   => 'Copy: Settings > [Text #1] field to Notes text',
						'value'  => '<div class="form-text">&raquo;{text_b1}&laquo;</div>',
						'order'  => 211,
					],
				],
			],
		],
	],
	'display' => [
		'type'    => 'group',
		'kind'    => 'checktab',
		'title'   => 'Display',
		'order'   => 99,
		'enqueue' => [ 'jquery-effects-pulsate', 'jquery-effects-highlight' ],
		'click'   => '
$box.prop( "checked" ) ?
$tab.show( "highlight", { color: "white" } ):
$tab.hide( "highlight", { color: "white" } );
',
		'items'   => [
			'reset' => [
				'type'   => 'checkbox',
				'tag'    => 'Reset form [jquery-effects-pulsate]',
				'class'  => 'reset',
				'extra'  => <<<'JS'
				onClick="( $el => $el.parent().effect( 'pulsate' ) )( jQuery( this ) )"
				JS,
			],
			'help' => [
				'type'   => 'html',
				'value'  => 'Help: [jquery-effects-highlight]',
			],
			'help_1' => [
				'type'   => 'checkbox',
				'tag'    => 'Tab 1',
				'class'  => 'form-text',
				'tab'    => '[Tab 1]: Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Elit eget gravida cum sociis. Malesuada fames ac turpis egestas maecenas pharetra.',
			],
			'help_2' => [
				'type'   => 'checkbox',
				'tag'    => 'Tab 2',
				'class'  => 'form-text',
				'tab'    => '[Tab 2]: Pharetra sit amet aliquam id diam. Nulla aliquet enim tortor at auctor. Nunc scelerisque viverra mauris in aliquam.',
			],
		],
	],
];
