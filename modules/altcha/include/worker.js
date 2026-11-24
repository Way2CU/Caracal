/**
 * ALTCHA Solver Worker
 *
 * Searches for the counter which solves challenge issued by the module. Key
 * is derived with PBKDF2 from challenge nonce followed by counter and compared
 * with key prefix from the challenge. Search space is shared between workers
 * by giving each one different starting counter and the same step.
 *
 * Copyright (c) 2026. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

'use strict';

var ALGORITHM = 'PBKDF2/SHA-256';
var MAXIMUM_COUNTER = 0xffffffff;


/**
 * Convert hexadecimal string to array of bytes.
 *
 * @param string value
 * @return object
 */
function hex_to_bytes(value) {
	var result = new Uint8Array(value.length / 2);

	for (var i = 0; i < result.length; i++)
		result[i] = parseInt(value.substr(i * 2, 2), 16);

	return result;
}


/**
 * Convert array of bytes to hexadecimal string.
 *
 * @param object bytes
 * @return string
 */
function bytes_to_hex(bytes) {
	var result = '';

	for (var i = 0; i < bytes.length; i++)
		result += (bytes[i] < 16 ? '0' : '') + bytes[i].toString(16);

	return result;
}


/**
 * Try counters until derived key starts with prefix from the challenge.
 *
 * @param object parameters
 * @param integer start
 * @param integer step
 * @return object
 */
async function solve(parameters, start, step) {
	var result = null;
	var nonce = hex_to_bytes(parameters.nonce);
	var prefix = parameters.keyPrefix.toLowerCase();
	var algorithm = {
			name: 'PBKDF2',
			hash: 'SHA-256',
			salt: hex_to_bytes(parameters.salt),
			iterations: parameters.cost
		};

	// password is nonce followed by counter as big-endian 32-bit number
	var password = new Uint8Array(nonce.length + 4);
	var view = new DataView(password.buffer);
	password.set(nonce);

	for (var counter = start; counter <= MAXIMUM_COUNTER && result === null; counter += step) {
		view.setUint32(nonce.length, counter, false);

		var key = await crypto.subtle.importKey('raw', password, 'PBKDF2', false, ['deriveBits']);
		var bits = await crypto.subtle.deriveBits(algorithm, key, parameters.keyLength * 8);
		var derived_key = bytes_to_hex(new Uint8Array(bits));

		if (derived_key.indexOf(prefix) == 0)
			result = {counter: counter, derivedKey: derived_key};
	}

	return result;
}


/**
 * Handle request to solve the challenge.
 *
 * @param object event
 */
self.onmessage = function(event) {
	var parameters = event.data.parameters;

	if (parameters.algorithm != ALGORITHM || !self.crypto || !self.crypto.subtle) {
		self.postMessage({error: 'Unsupported algorithm or browser.'});

	} else {
		solve(parameters, event.data.start, event.data.step)
			.then(function(solution) {
				if (solution === null)
					self.postMessage({error: 'Solution was not found.'}); else
					self.postMessage({solution: solution});
			})
			.catch(function(error) {
				self.postMessage({error: String(error)});
			});
	}
};
