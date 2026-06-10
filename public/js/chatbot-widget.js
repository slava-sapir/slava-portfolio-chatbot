( function () {
	'use strict';

	if ( typeof window.SPC_CHATBOT === 'undefined' || ! window.SPC_CHATBOT.enabled ) {
		return;
	}

	var root = document.querySelector( '[data-spc-chatbot]' );

	if ( ! root ) {
		return;
	}

	var launcher = root.querySelector( '.spc-chatbot__launcher' );
	var panel = root.querySelector( '.spc-chatbot__panel' );
	var closeButton = root.querySelector( '.spc-chatbot__close' );
	var form = root.querySelector( '[data-spc-form]' );
	var input = root.querySelector( '[data-spc-input]' );
	var messages = root.querySelector( '[data-spc-messages]' );
	var quickReplies = root.querySelector( '[data-spc-quick-replies]' );
	var links = root.querySelector( '[data-spc-links]' );
	var lead = root.querySelector( '[data-spc-lead]' );
	var leadForm = root.querySelector( '[data-spc-lead-form]' );
	var leadStatus = root.querySelector( '[data-spc-lead-status]' );
	var survey = root.querySelector( '[data-spc-survey]' );
	var surveyForm = root.querySelector( '[data-spc-survey-form]' );
	var surveySkip = root.querySelector( '[data-spc-survey-skip]' );
	var conversationId = getConversationId();
	var visitorContext = getVisitorContext();
	var isSending = false;

	function getConversationId() {
		var existing = window.sessionStorage.getItem( 'spcConversationId' );

		if ( existing ) {
			return existing;
		}

		var generated = 'spc-' + Date.now().toString( 36 ) + '-' + Math.random().toString( 36 ).slice( 2, 10 );
		window.sessionStorage.setItem( 'spcConversationId', generated );

		return generated;
	}

	function openPanel() {
		panel.hidden = false;
		launcher.setAttribute( 'aria-expanded', 'true' );
		maybeShowSurvey();
		window.setTimeout( function () {
			input.focus();
		}, 0 );
	}

	function closePanel() {
		panel.hidden = true;
		launcher.setAttribute( 'aria-expanded', 'false' );
		launcher.focus();
	}

	function appendMessage( text, type ) {
		var message = document.createElement( 'div' );
		message.className = 'spc-chatbot__message spc-chatbot__message--' + type;

		if ( type.indexOf( 'assistant' ) === 0 ) {
			appendFormattedText( message, text );
		} else {
			message.textContent = text;
		}

		messages.appendChild( message );
		messages.scrollTop = messages.scrollHeight;

		return message;
	}

	function appendFormattedText( element, text ) {
		var lines = String( text || '' ).split( /\r?\n/ );
		var list = null;

		lines.forEach( function ( rawLine ) {
			var line = rawLine.trim();

			if ( ! line ) {
				list = null;
				return;
			}

			if ( /^(\d+\.|-|\*)\s+/.test( line ) ) {
				if ( ! list ) {
					list = document.createElement( 'ul' );
					element.appendChild( list );
				}

				var item = document.createElement( 'li' );
				appendTextWithLinks( item, line.replace( /^(\d+\.|-|\*)\s+/, '' ) );
				list.appendChild( item );
				return;
			}

			list = null;
			var paragraph = document.createElement( 'p' );
			appendTextWithLinks( paragraph, line );
			element.appendChild( paragraph );
		} );
	}

	function appendTextWithLinks( element, text ) {
		var pattern = /(https?:\/\/[^\s)]+)([).,!?;:]*)/g;
		var lastIndex = 0;
		var match;

		while ( ( match = pattern.exec( text ) ) !== null ) {
			if ( match.index > lastIndex ) {
				element.appendChild( document.createTextNode( text.slice( lastIndex, match.index ) ) );
			}

			var link = document.createElement( 'a' );
			if ( isAllowedLink( match[1] ) ) {
				link.href = match[1];
				link.textContent = match[1];
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
				element.appendChild( link );
			} else {
				element.appendChild( document.createTextNode( match[1] ) );
			}

			if ( match[2] ) {
				element.appendChild( document.createTextNode( match[2] ) );
			}

			lastIndex = pattern.lastIndex;
		}

		if ( lastIndex < text.length ) {
			element.appendChild( document.createTextNode( text.slice( lastIndex ) ) );
		}
	}

	function setQuickReplies( replies ) {
		quickReplies.innerHTML = '';

		if ( ! Array.isArray( replies ) ) {
			return;
		}

		replies.forEach( function ( reply ) {
			if ( ! reply ) {
				return;
			}

			var button = document.createElement( 'button' );
			button.type = 'button';
			button.textContent = reply;
			button.setAttribute( 'data-spc-quick-reply', reply );
			quickReplies.appendChild( button );
		} );
	}

	function setLinks( suggestedLinks, sourceLabels ) {
		links.innerHTML = '';

		if ( ! Array.isArray( suggestedLinks ) || suggestedLinks.length === 0 ) {
			links.hidden = true;
			return;
		}

		var title = document.createElement( 'div' );
		title.className = 'spc-chatbot__links-title';
		title.textContent = Array.isArray( sourceLabels ) && sourceLabels.length
			? 'Sources: ' + sourceLabels.join( ', ' )
			: 'Sources';
		links.appendChild( title );
		var renderedLinks = 0;

		( suggestedLinks || [] ).forEach( function ( item ) {
			if ( ! item || ! item.url ) {
				return;
			}

			var link = document.createElement( 'a' );
			link.href = item.url;
			link.textContent = item.title || item.url;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';

			if ( ! isAllowedLink( item.url ) ) {
				return;
			}

			links.appendChild( link );
			renderedLinks += 1;
		} );

		if ( renderedLinks === 0 ) {
			links.innerHTML = '';
			links.hidden = true;
			return;
		}

		links.hidden = false;
	}

	function isAllowedLink( url ) {
		var allowedDomains = window.SPC_CHATBOT.allowedLinkDomains || [];

		if ( ! Array.isArray( allowedDomains ) || allowedDomains.length === 0 ) {
			return false;
		}

		try {
			var parsed = new URL( url );
			var host = parsed.hostname.toLowerCase();

			return allowedDomains.some( function ( domain ) {
				domain = String( domain || '' ).toLowerCase();
				return host === domain || host.endsWith( '.' + domain );
			} );
		} catch ( error ) {
			return false;
		}
	}

	function mergeAllowedLinkDomains( domains ) {
		if ( ! Array.isArray( domains ) ) {
			return;
		}

		var existing = Array.isArray( window.SPC_CHATBOT.allowedLinkDomains )
			? window.SPC_CHATBOT.allowedLinkDomains
			: [];

		domains.forEach( function ( domain ) {
			domain = String( domain || '' ).toLowerCase();

			if ( domain && existing.indexOf( domain ) === -1 ) {
				existing.push( domain );
			}
		} );

		window.SPC_CHATBOT.allowedLinkDomains = existing;
	}

	function setLeadVisibility( visible ) {
		if ( lead ) {
			lead.hidden = ! visible;
		}
	}

	function setSending( sending ) {
		isSending = sending;
		form.querySelector( 'button' ).disabled = sending;
		input.disabled = sending;
	}

	function getVisitorContext() {
		try {
			return JSON.parse( window.sessionStorage.getItem( 'spcVisitorContext' ) || '{}' );
		} catch ( error ) {
			return {};
		}
	}

	function saveVisitorContext( context ) {
		visitorContext = context || {};
		window.sessionStorage.setItem( 'spcVisitorContext', JSON.stringify( visitorContext ) );
		window.sessionStorage.setItem( 'spcSurveySeen', '1' );
	}

	function maybeShowSurvey() {
		if ( ! survey || window.sessionStorage.getItem( 'spcSurveySeen' ) === '1' ) {
			return;
		}

		survey.hidden = false;
	}

	function hideSurvey() {
		if ( survey ) {
			survey.hidden = true;
		}
	}

	function sendMessage( text ) {
		text = ( text || '' ).trim();

		if ( ! text || isSending ) {
			return;
		}

		appendMessage( text, 'user' );
		input.value = '';
		input.style.height = '';
		setSending( true );
		setLinks( [], [] );
		setLeadVisibility( false );

		var loadingMessage = appendMessage( 'Thinking...', 'assistant spc-chatbot__message--loading' );

		window.fetch( window.SPC_CHATBOT.restUrl + 'chat', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.SPC_CHATBOT.nonce || ''
			},
			body: JSON.stringify( {
				message: text,
				conversation_id: conversationId,
				language: visitorContext.language || 'en',
				visitor_type: visitorContext.visitor_type || '',
				interest_area: visitorContext.interest_area || '',
				source_page: window.location.href
			} )
		} )
			.then( function ( response ) {
				return response.json().then( function ( data ) {
					if ( ! response.ok ) {
						throw new Error( data.message || 'The assistant could not respond.' );
					}

					return data;
				} );
			} )
			.then( function ( data ) {
				loadingMessage.remove();
				conversationId = data.conversation_id || conversationId;
				window.sessionStorage.setItem( 'spcConversationId', conversationId );
				mergeAllowedLinkDomains( data.allowed_link_domains || [] );
				var assistantMessage = appendMessage( data.assistant_message || 'I could not find an answer for that.', 'assistant' );
				if ( data.show_lead_form ) {
					assistantMessage.setAttribute( 'data-spc-lead-intro', 'true' );
				}
				setQuickReplies( data.quick_replies || [] );
				setLinks( data.suggested_links || [], data.source_labels || [] );
				setLeadVisibility( !! data.show_lead_form );
			} )
			.catch( function ( error ) {
				loadingMessage.remove();
				appendMessage( error.message || 'Something went wrong. Please try again.', 'error' );
			} )
			.finally( function () {
				setSending( false );
				input.focus();
			} );
	}

	launcher.addEventListener( 'click', function () {
		if ( panel.hidden ) {
			openPanel();
		} else {
			closePanel();
		}
	} );

	closeButton.addEventListener( 'click', closePanel );

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		sendMessage( input.value );
	} );

	input.addEventListener( 'input', function () {
		input.style.height = 'auto';
		input.style.height = Math.min( input.scrollHeight, 120 ) + 'px';
	} );

	input.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Enter' && ! event.shiftKey ) {
			event.preventDefault();
			sendMessage( input.value );
		}
	} );

	quickReplies.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-spc-quick-reply]' );

		if ( button ) {
			sendMessage( button.getAttribute( 'data-spc-quick-reply' ) );
		}
	} );

	if ( leadForm ) {
		leadForm.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			submitLead();
		} );
	}

	if ( surveyForm ) {
		surveyForm.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var formData = new window.FormData( surveyForm );
			saveVisitorContext( {
				language: formData.get( 'language' ) || 'en',
				visitor_type: formData.get( 'visitor_type' ) || '',
				interest_area: formData.get( 'interest_area' ) || ''
			} );
			hideSurvey();
		} );
	}

	if ( surveySkip ) {
		surveySkip.addEventListener( 'click', function () {
			saveVisitorContext( {
				language: 'en',
				visitor_type: '',
				interest_area: ''
			} );
			hideSurvey();
		} );
	}

	function submitLead() {
		var submitButton = leadForm.querySelector( 'button[type="submit"]' );
		var formData = new window.FormData( leadForm );
		var payload = {
			name: formData.get( 'name' ) || '',
			email: formData.get( 'email' ) || '',
			company: formData.get( 'company' ) || '',
			country: formData.get( 'country' ) || '',
			visitor_type: visitorContext.visitor_type || '',
			interest_area: formData.get( 'interest_area' ) || visitorContext.interest_area || '',
			message: formData.get( 'message' ) || '',
			conversation_id: conversationId,
			source_page: window.location.href,
			consent_given: !! formData.get( 'consent_given' )
		};

		if ( leadStatus ) {
			leadStatus.textContent = '';
		}

		submitButton.disabled = true;

		window.fetch( window.SPC_CHATBOT.restUrl + 'lead', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.SPC_CHATBOT.nonce || ''
			},
			body: JSON.stringify( payload )
		} )
			.then( function ( response ) {
				return response.json().then( function ( data ) {
					if ( ! response.ok || ! data.success ) {
						throw new Error( data.message || 'Could not submit your details.' );
					}

					return data;
				} );
			} )
			.then( function ( data ) {
				var thankYouMessage = data.thank_you_message || 'Thanks. Your details were sent.';
				var leadIntroMessage = messages.querySelector( '[data-spc-lead-intro="true"]' );

				leadForm.reset();
				if ( leadStatus ) {
					leadStatus.textContent = '';
				}
				setLeadVisibility( false );

				if ( leadIntroMessage ) {
					leadIntroMessage.textContent = thankYouMessage;
					leadIntroMessage.removeAttribute( 'data-spc-lead-intro' );
				} else {
					appendMessage( thankYouMessage, 'assistant' );
				}
			} )
			.catch( function ( error ) {
				if ( leadStatus ) {
					leadStatus.textContent = error.message || 'Something went wrong. Please try again.';
				}
			} )
			.finally( function () {
				submitButton.disabled = false;
			} );
	}

	window.SPC_CHATBOT.isLoaded = true;
}() );
