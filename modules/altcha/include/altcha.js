/**
 * ALTCHA Widget JavaScript
 * Caracal Development Framework
 *
 * Copyright (c) 2026. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 *
 * Widget markup is rendered by the module. This script gets challenge from
 * the module, solves it in background workers and stores result in hidden
 * field of the widget. State of the widget is available in `data-state`
 * attribute of its container: `unverified`, `verifying`, `verified`, `error`
 * or `expired`. Control of the widget is made of plain elements on purpose,
 * site styles for labels and inputs would otherwise apply to it.
 *
 * Forms which submit data through JavaScript should trigger `submit-rejected`
 * event on form element when server rejects submission, as each verification
 * can be used only once.
 *
 * Widget triggers following events on form element:
 * - `submit-held` when submission is put on hold until form is verified. Form
 *   is submitted again once verification is done;
 * - `submit-dropped` when submission which was on hold will not continue.
 */

var Caracal = Caracal || new Object();
Caracal.Altcha = Caracal.Altcha || new Object();


Caracal.Altcha.Widget = function(container) {
	var self = this;

	self._container = container;
	self._form = null;
	self._field = null;
	self._control = null;
	self._auto = null;
	self._workers = [];
	self._request = null;
	self._challenge = null;
	self._expire_timeout = null;
	self._submit_pending = false;
	self._submitter = null;

	self.handler = new Object();

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		self._form = self._container.closest('form');
		self._field = self._container.querySelector('input[type=hidden]');
		self._control = self._container.querySelector('div.control');
		self._auto = self._container.dataset.auto;

		// connect events
		self._control.addEventListener('click', self.handler.control_click);
		self._control.addEventListener('keydown', self.handler.control_keydown);

		if (self._form !== null) {
			self._form.addEventListener('submit', self.handler.form_submit, {capture: true});
			self._form.addEventListener('reset', self.handler.form_reset);
			self._form.addEventListener('submit-rejected', self.handler.form_reset);

			if (self._auto == 'onfocus')
				self._form.addEventListener('focusin', self.handler.form_focus);
		}

		if (self._auto == 'onload')
			self.verify();
	};

	/**
	 * Change state of the widget.
	 *
	 * @param string state
	 */
	self._set_state = function(state) {
		self._container.dataset.state = state;
		self._container.removeAttribute('data-attention');
		self._control.setAttribute('aria-checked', state == 'verified' ? 'true' : 'false');
		self._control.setAttribute('aria-busy', state == 'verifying' ? 'true' : 'false');

		if (state != 'verified')
			self._field.value = '';
	};

	/**
	 * Stop everything running in the background.
	 */
	self._stop = function() {
		self._workers.forEach(function(worker) {
			worker.terminate();
		});
		self._workers = [];

		if (self._request !== null) {
			self._request.onload = null;
			self._request.onerror = null;
			self._request.abort();
			self._request = null;
		}

		clearTimeout(self._expire_timeout);
	};

	/**
	 * Give up on submission which was waiting for verification.
	 */
	self._drop_submission = function() {
		if (self._submit_pending) {
			self._submit_pending = false;
			self._form.dispatchEvent(new CustomEvent('submit-dropped'));
		}
	};

	/**
	 * Stop verification and report failure.
	 */
	self._fail = function() {
		self._stop();
		self._drop_submission();
		self._set_state('error');
	};

	/**
	 * Start verification, unless it's already running or completed.
	 */
	self.verify = function() {
		var state = self._container.dataset.state;

		if (state != 'verifying' && state != 'verified') {
			self._stop();
			self._set_state('verifying');

			// get new challenge from the module
			self._request = new XMLHttpRequest();
			self._request.onload = self.handler.challenge_load;
			self._request.onerror = self._fail;
			self._request.open('GET', self._container.dataset.challenge, true);
			self._request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
			self._request.send();
		}
	};

	/**
	 * Return widget to its initial state.
	 */
	self.reset = function() {
		self._stop();
		self._drop_submission();
		self._set_state('unverified');
	};

	/**
	 * Handle challenge arriving from the module and start solving it.
	 */
	self.handler.challenge_load = function() {
		var request = self._request;
		var challenge = null;
		self._request = null;

		try {
			challenge = JSON.parse(request.responseText);

		} catch (error) {
			challenge = null;
		}

		if (request.status != 200 || challenge === null || !challenge.parameters || !window.Worker) {
			self._fail();

		} else {
			// share search space equally between workers
			var count = Math.max(1, Math.min(navigator.hardwareConcurrency || 2, 8));
			self._challenge = challenge;

			for (var i = 0; i < count; i++) {
				var worker = new Worker(self._container.dataset.worker);
				worker.onmessage = self.handler.worker_message;
				worker.onerror = self._fail;
				worker.postMessage({parameters: challenge.parameters, start: i, step: count});
				self._workers.push(worker);
			}

			// challenge can't be used once it expires
			var lifetime = challenge.parameters.expiresAt * 1000 - Date.now();
			self._expire_timeout = setTimeout(self.handler.expire, Math.max(lifetime, 0));
		}
	};

	/**
	 * Handle result from one of the workers.
	 *
	 * @param object event
	 */
	self.handler.worker_message = function(event) {
		if (!event.data.solution) {
			self._fail();

		} else {
			var payload = {
					challenge: {
						parameters: self._challenge.parameters,
						signature: self._challenge.signature
					},
					solution: event.data.solution
				};

			// remaining workers are no longer needed
			self._workers.forEach(function(worker) {
				worker.terminate();
			});
			self._workers = [];

			self._set_state('verified');
			self._field.value = btoa(JSON.stringify(payload));

			// continue with submission which was waiting for verification
			if (self._submit_pending) {
				self._submit_pending = false;
				self._form.requestSubmit(self._submitter);
			}
		}
	};

	/**
	 * Handle challenge running out of time, solved or not.
	 */
	self.handler.expire = function() {
		self._stop();
		self._drop_submission();
		self._set_state('expired');
	};

	/**
	 * Handle visitor clicking on the control.
	 *
	 * @param object event
	 */
	self.handler.control_click = function(event) {
		self.verify();
	};

	/**
	 * Handle visitor using the control with keyboard.
	 *
	 * @param object event
	 */
	self.handler.control_keydown = function(event) {
		if (event.key == ' ' || event.key == 'Enter') {
			event.preventDefault();
			self.verify();
		}
	};

	/**
	 * Handle visitor starting to fill the form.
	 *
	 * @param object event
	 */
	self.handler.form_focus = function(event) {
		self._form.removeEventListener('focusin', self.handler.form_focus);
		self.verify();
	};

	/**
	 * Handle form being submitted. When verification is running, or widget is
	 * the one to start it, submission is put on hold until form is verified.
	 * Otherwise form can't be submitted before visitor verifies it.
	 *
	 * @param object event
	 */
	self.handler.form_submit = function(event) {
		var state = self._container.dataset.state;

		if (state != 'verified') {
			event.preventDefault();
			event.stopImmediatePropagation();

			if (state == 'verifying' || self._auto != 'off') {
				self._submit_pending = true;
				self._submitter = event.submitter || null;
				self._form.dispatchEvent(new CustomEvent('submit-held'));
				self.verify();

			} else {
				// point visitor to the step which is missing
				self._container.dataset.attention = '1';
				self._control.focus();
			}
		}
	};

	/**
	 * Handle form being cleared or rejected by the server.
	 *
	 * @param object event
	 */
	self.handler.form_reset = function(event) {
		self.reset();

		// verification on load is expected to be ready for the next submission
		if (self._auto == 'onload')
			self.verify();
	};

	// finalize object
	self._init();
}


// create widgets
window.addEventListener('load', function() {
	Caracal.Altcha.list = [];

	document.querySelectorAll('div.altcha-widget').forEach(function(container) {
		Caracal.Altcha.list.push(new Caracal.Altcha.Widget(container));
	});
});
