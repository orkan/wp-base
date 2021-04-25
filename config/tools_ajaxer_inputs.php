<?php
/*
 * This file is part of the orkan/wp-base package.
 * Copyright (c) 2024 Orkan <orkans+wpbase@gmail.com>
 */

/**
 * Settings tool: Ajaxer.
 */
return [
	'info' => [
		'type'   => 'html',
		'html'   => <<<HTML
		<h2>Ajax example</h2>
		Choose any of Plugin::cfg() keys to display coresponding value.<br>
		Enable JS verbosing by typing in console: ork.debug=1<br>
		HTML,
		'desc'   => 'Inputs defined in: ' . __FILE__,
	],
	'hr1' => [
		'type' => 'html',
		'html' => '<hr>',
	],
	'selector' => [
		'type'  => 'group',
		'items' => [
			'selector_select' => [
				'type'  => 'select',
				'label' => 'Choose ',
				'items' => [ '' => 'Config:' ],
			],
			'selector_spinner' => [
				'type' => 'html',
				'html' => '<span id="selector_spin" class="ork-spinner"></span>',
			],
			'selector_result' => [
				'type'     => 'text',
				'label'    => 'Response: ',
				'style'    => 'width: 30em;',
				'readonly' => true,
			],
		],
	],
	'hr2' => [
		'type' => 'html',
		'html' => '<hr>',
	],
	'texter' => [
		'type'  => 'group',
		'items' => [
			'texter_error' => [
				'type' => 'checkbox',
				'tag'  => 'Throw errors instead of normal response',
			],
			'texter_text' => [
				'type'  => 'text',
				'label' => 'Type: ',
				'style' => 'width: 30em;',
			],
			'texter_spinner' => [
				'type' => 'html',
				'html' => '<span id="texter_spin" class="ork-spinner"></span>',
			],
			'texter_result' => [
				'type'     => 'text',
				'label'    => 'Response: ',
				'style'    => 'width: 30em;',
				'readonly' => true,
			],
		],
	],
];
