/*
 * This file is part of the orkan/wp-base package.
 * Copyright (c) 2024 Orkan <orkans+wpbase@gmail.com>
 */
/* DO NOT EDIT - AUTO-GENERATED FROM: wp-content/plugins/ork-base1/assets/js/src/settings.js */
window.ork = window.ork || {};

(function ($) {

	/*
	 * ---------------------------------------------------------------------------------------------------------
	 * Ork | Base
	 */
	ork.Base = {
		/**
		 * Get element ID from name.
		 */
		getId( name ) {
			return '#' + ork.name2id[name];
		},
	
		/**
		 * Get element from name.
		 */
		getElement( name ) {
			return document.getElementById( ork.name2id[name] );
		},
	
		/**
		 * Logger.
		 */
		log( msg, type = 'log' ) {
			if( 'debug' === type && !ork.debug ) {
				return;
			}
			else {
				type = 'log';
			}
	
			console[type]( msg );
		},
	
		debug( msg ) {
			this.log( msg, 'debug' );
		},
	
		/**
		 * Clear FORM inputs.
		 */
		clearForm( form ) {
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
		 * Build query string from object.
		 */
		buildHttpQuery( obj ) {
			const Query = new URLSearchParams();
	
			for( const [name, value] of Object.entries( obj ) ) {
				Query.append( name, value );
			}
	
			return Query.toString();
		},
	
		/**
		 * [Input] Show/hide spinner
		 */
		showSpinner( id, show = true ) {
			$( id + '_spin' ).toggleClass( 'is-active', show );
		},
	
		/**
		 * Must be called as: await usleep(1); from within another async function! :(
		 */
		async usleep( usec ) {
			return await new Promise( (resolve, reject) => setTimeout( () => resolve(1), usec ) );
		},
	};

	/*
	 * ---------------------------------------------------------------------------------------------------------
	 * Ork | Ajax
	 */
	ork.Ajax = {
		/**
		 * Ajax request
		 */
		async fetch( action, data = {}, opts = {} ) {
			data.action = action;
			data[ork.nonce.name] = ork.nonce.action;
	
			// Deaults...
			opts = { ...{
				method: 'POST',
				signal:  null,
				spinner: '',
				body: this.getFormData( data ),
			}, ...opts };
	
			let json, status;
			this.spinner( opts.spinner, true );
	
			const Response = await fetch( ork.url, opts )
				.then( Response => {
					status = `[${Response.status}] ${Response.statusText}`;
					ork.Base.debug( Response );
					return Response.text();
				})
				.catch( E => {
					throw new Error( E ); // Connection errors
				});
	
			this.spinner( opts.spinner, false );
	
			try {
				json = JSON.parse( Response );
			} catch( E ) {
				throw new Error( E ); // Data errors
			}
	
			if( !json || !json.success ) {
				throw new Error( json.data || status ); // WP errors
			}
	
			return json.data;
		},
	
		/**
		 * Convert Object to FormData instance.
		 */
		getFormData( obj ) {
			const Data = new FormData();
			for( const [name, value] of Object.entries( obj ) ) {
				Data.append( name, value );
			}
			return Data;
		},
	
		/**
		 * [Input] Show/hide spinner
		 */
		spinner( id, show = true, className = 'is-active' ) {
			if( id ) {
				const ClassList = document.querySelector( id ).classList;
				show ? ClassList.add( className ) : ClassList.remove( className );
			}
		},
	};


	/*
	 * ---------------------------------------------------------------------------------------------------------
	 * onLoad
	 */
	$(function() {

	});

})( jQuery );
