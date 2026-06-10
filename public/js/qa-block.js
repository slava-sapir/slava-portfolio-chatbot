( function () {
	'use strict';

	if ( typeof window.SPC_QA_BLOCK === 'undefined' ) {
		return;
	}

	var blocks = document.querySelectorAll( '[data-spc-qa]' );

	if ( ! blocks.length ) {
		return;
	}

	blocks.forEach( initBlock );

	function initBlock( block ) {
		var form = block.querySelector( '[data-spc-qa-form]' );
		var input = block.querySelector( '[data-spc-qa-input]' );
		var answer = block.querySelector( '[data-spc-qa-answer]' );
		var sources = block.querySelector( '[data-spc-qa-sources]' );
		var suggestions = block.querySelector( '[data-spc-qa-suggestions]' );
		var pageId = block.getAttribute( 'data-page-id' ) || '';
		var isSending = false;

		if ( ! form || ! input || ! answer ) {
			return;
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			sendQuestion( input.value );
		} );

		if ( suggestions ) {
			suggestions.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '[data-spc-qa-question]' );

				if ( button ) {
					sendQuestion( button.getAttribute( 'data-spc-qa-question' ) );
				}
			} );
		}

		function sendQuestion( text ) {
			text = ( text || '' ).trim();

			if ( ! text || isSending ) {
				return;
			}

			setSending( true );
			setAnswer( 'Thinking...', true );
			setSources( [] );
			input.value = '';

			window.fetch( window.SPC_QA_BLOCK.restUrl + 'qa', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.SPC_QA_BLOCK.nonce || ''
				},
				body: JSON.stringify( {
					message: text,
					page_id: pageId,
					language: window.SPC_QA_BLOCK.defaultLanguage || 'en'
				} )
			} )
				.then( function ( response ) {
					return response.json().then( function ( data ) {
						if ( ! response.ok ) {
							throw new Error( data.message || 'The page Q&A could not respond.' );
						}

						return data;
					} );
				} )
				.then( function ( data ) {
					mergeAllowedLinkDomains( data.allowed_link_domains || [] );
					setAnswer( data.answer || 'I could not find an answer for that.', false );
					setSources( data.suggested_links || [] );
				} )
				.catch( function ( error ) {
					setAnswer( error.message || 'Something went wrong. Please try again.', false, true );
				} )
				.finally( function () {
					setSending( false );
					input.focus();
				} );
		}

		function setSending( sending ) {
			var button = form.querySelector( 'button' );
			isSending = sending;
			input.disabled = sending;

			if ( button ) {
				button.disabled = sending;
			}
		}

		function setAnswer( text, loading, error ) {
			answer.innerHTML = '';
			answer.hidden = false;
			answer.classList.toggle( 'spc-qa__answer--loading', !! loading );
			answer.classList.toggle( 'spc-qa__answer--error', !! error );
			appendTextWithLinks( answer, text );
		}

		function setSources( links ) {
			if ( ! sources ) {
				return;
			}

			sources.innerHTML = '';

			if ( ! Array.isArray( links ) || ! links.length ) {
				sources.hidden = true;
				return;
			}

			var title = document.createElement( 'div' );
			title.className = 'spc-qa__sources-title';
			title.textContent = 'Sources';
			sources.appendChild( title );

			var rendered = 0;

			links.forEach( function ( item ) {
				if ( ! item || ! item.url || ! isAllowedLink( item.url ) ) {
					return;
				}

				var link = document.createElement( 'a' );
				link.href = item.url;
				link.textContent = item.title || item.url;
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
				sources.appendChild( link );
				rendered += 1;
			} );

			if ( rendered === 0 ) {
				sources.hidden = true;
				return;
			}

			sources.hidden = false;
		}
	}

	function appendTextWithLinks( element, text ) {
		var pattern = /(https?:\/\/[^\s)]+)([).,!?;:]*)/g;
		var lastIndex = 0;
		var match;

		while ( ( match = pattern.exec( text ) ) !== null ) {
			if ( match.index > lastIndex ) {
				element.appendChild( document.createTextNode( text.slice( lastIndex, match.index ) ) );
			}

			if ( isAllowedLink( match[1] ) ) {
				var link = document.createElement( 'a' );
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

	function isAllowedLink( url ) {
		var allowedDomains = window.SPC_QA_BLOCK.allowedLinkDomains || [];

		if ( ! Array.isArray( allowedDomains ) || ! allowedDomains.length ) {
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

		var existing = Array.isArray( window.SPC_QA_BLOCK.allowedLinkDomains )
			? window.SPC_QA_BLOCK.allowedLinkDomains
			: [];

		domains.forEach( function ( domain ) {
			domain = String( domain || '' ).toLowerCase();

			if ( domain && existing.indexOf( domain ) === -1 ) {
				existing.push( domain );
			}
		} );

		window.SPC_QA_BLOCK.allowedLinkDomains = existing;
	}
}() );
