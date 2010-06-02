/*

	YouTube Player Controls
	Copyright (c) 2010. by way2CU, http://www.way2cu.com
	
	Author: MeanEYE[rcf], http://rcf-group.com
	
*/

function onYouTubePlayerReady(playerId) {
	YouTube_Controls.init(playerId);
}

YouTube_Controls = {
	player = null,
	
	/**
	 * Initialize event listeners and controls for
	 * @param string player_id 
	 */
	init: function(player_id) {
		this.player = document.getElementById(player_id);
		
		player.addEventListener("onStateChange", this.onPlayerStateChange);
		player.addEventListener("onPlaybackQualityChange", this.onPlaybackQualityChange);
		player.addEventListener("onError", this.onPlayerError);
	},

	/**
	 * Event fired when player state is changed
	 * @param integer state
	 */
	onPlayerStateChange: function(state) {
	},
	
	/**
	 * Event fired when video quality is changed
	 * @param string quality
	 */
	onPlaybackQualityChange: function(quality) {
	},
	
	/**
	 * Event fired when error in player occurs
	 * @param integer code 
	 */
	onPlayerError: function(code) {
	}
}