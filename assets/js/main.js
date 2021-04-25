/*
 * This file is part of the orkan/wp-base package.
 * Copyright (c) 2024 Orkan <orkans+wpbase@gmail.com>
 */
/* DO NOT EDIT - AUTO-GENERATED FROM: wp-content\plugins\ork-base\assets\js/src/main.js */
window.ork = window.ork || {};

(function ($) {

	/*
	 * ---------------------------------------------------------------------------------------------------------
	 * Ork | Base
	 */
	$.extend( ork, {
	
		/**
		 * Get element ID from name.
		 */
		getId: function( name ) {
			return '#' + this.name2id[name];
		},
	
		/**
		 * Get element from name.
		 */
		getElement: function( name ) {
			return document.getElementById( this.name2id[name] );
		},
	
		/**
		 * Logger.
		 */
		log: function( msg, type = 'log' ) {
			if( 'debug' === type && !this.isDebug ) {
				return;
			}
			else {
				type = 'log';
			}
	
			console[type]( msg );
		},
	
		/**
		 * Check if HTML element is empty.
		 */
		isEmpty: function( el ) {
			return $( el ).length ? !$.trim( $( el ).html() ) : true;
		},
	
		/**
		 * Clear FORM inputs.
		 */
		clearForm: function( form ) {
			form.reset();
			Array.from( form.elements ).forEach( input => {
				if( input.disabled ) {
					return;
				}
				switch( input.type.toLowerCase() ) {
					case 'text':
					case 'password':
					case 'textarea':
					case 'hidden':
						input.value = '';
						break;
	
					case 'radio':
					case 'checkbox':
						input.checked = false;
						break;
	
					case 'select-one':
					case 'select-multi':
						input.selectedIndex = -1;
						break;
				}
			});
		},
	
		/**
		 * Convert Object to FormData instance.
		 */
		buildFormData( arr ) {
			var Data = new FormData();
			$.each( arr, ( name, value ) => Data.append( name, value ) );
			return Data;
		},
	
		/**
		 * Build query string from array.
		 */
		buildHttpQuery( arr ) {
			var Query = new URLSearchParams();
	
			$.each( arr, ( name, value ) => {
				Query.append( name, value );
			});
	
			return Query.toString();
		},
	
		/**
		 * Must be called as: await usleep(1); from within another async function! :(
		 */
		async usleep( usec ) {
			return await new Promise( (resolve, reject) => setTimeout( () => resolve(1), usec ) );
		},
	});

	/*
	 * ---------------------------------------------------------------------------------------------------------
	 * Ork | Ajax
	 */
	ork.Ajax = {
		/**
		 * Append required elements to Request object
		 */
		prepareRequest: function( request, args = {} ) {
			request[ork.nonce.name] = ork.nonce.action;
			return $.extend( request, args );
		},
	
		/**
		 * [Input] Show/hide spinner
		 */
		showSpinner: function( id, show = true ) {
			$( id + '_spin' ).toggleClass( 'is-active', show );
		},
	};


})( jQuery );
