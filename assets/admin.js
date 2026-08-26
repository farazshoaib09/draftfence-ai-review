(function() {
	'use strict';

	var data = window.draftFenceAiReviewConnect || {};
	var strings = data.strings || {};
	var map = data.configs || {};
	var username = document.getElementById('draftfence-ai-review-connect-username');
	var password = document.getElementById('draftfence-ai-review-connect-password');
	var generated = document.getElementById('draftfence-ai-review-generated-header');
	var message = document.getElementById('draftfence-ai-review-connect-message');

	function setMessage(text, type) {
		if (!message) {
			return;
		}

		message.textContent = text;
		message.className = 'draftfence-ai-review-js-message is-' + type;
	}

	function base64Utf8(value) {
		return window.btoa(unescape(encodeURIComponent(value)));
	}

	function updateSnippets() {
		var user = username ? (username.value || '').replace(/^\s+|\s+$/g, '') : '';
		var pass = password ? (password.value || '') : '';
		var credential = 'YOUR_BASE64_CREDENTIAL';
		var header;

		if (!user || !pass) {
			setMessage(strings.missingCredentials || 'Enter both the WordPress username and Application Password first.', 'error');
		} else {
			try {
				credential = base64Utf8(user + ':' + pass);
				setMessage(strings.credentialGenerated || 'Credential generated locally. Copy the updated config below.', 'success');
			} catch (error) {
				setMessage(strings.credentialError || 'Your browser could not generate the Base64 credential.', 'error');
				return;
			}
		}

		header = 'Basic ' + credential;
		if (generated) {
			generated.value = 'Authorization: ' + header;
		}

		Object.keys(map).forEach(function(id) {
			var node = document.getElementById(id);
			var text;

			if (!node) {
				return;
			}

			text = String(map[id]).split('YOUR_BASE64_CREDENTIAL').join(credential);
			if ('INPUT' === node.tagName) {
				node.value = text;
			} else if ('TEXTAREA' === node.tagName) {
				node.value = text;
			} else {
				node.textContent = text;
			}
		});
	}

	function bindCredentialGenerator() {
		var button = document.getElementById('draftfence-ai-review-generate-credential');
		var toggle = document.getElementById('draftfence-ai-review-toggle-password');

		if (button) {
			button.addEventListener('click', updateSnippets);
		}

		if (username) {
			username.addEventListener('input', updateSnippets);
		}

		if (password) {
			password.addEventListener('input', updateSnippets);
		}

		if (toggle) {
			toggle.addEventListener('click', function() {
				if (password) {
					password.type = 'password' === password.type ? 'text' : 'password';
				}
			});
		}
	}

	function bindCopyButtons() {
		Array.prototype.forEach.call(document.querySelectorAll('.draftfence-ai-review-copy-button'), function(button) {
			button.addEventListener('click', function() {
				var target = document.getElementById(button.getAttribute('data-copy-target'));
				var text = target && ('INPUT' === target.tagName || 'TEXTAREA' === target.tagName) ? target.value : (target ? target.textContent : '');
				var old = button.textContent;

				function done() {
					button.textContent = strings.copied || 'Copied';
					window.setTimeout(function() {
						button.textContent = old;
					}, 1200);
				}

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(done).catch(function() {
						window.prompt(strings.copyPrompt || 'Copy this value:', text);
					});
				} else {
					window.prompt(strings.copyPrompt || 'Copy this value:', text);
				}
			});
		});
	}

	bindCredentialGenerator();
	bindCopyButtons();
})();
