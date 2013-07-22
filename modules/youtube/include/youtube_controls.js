/**
 *
 * YouTube Player Controls
 * Copyright (c) 2013. by Way2CU
 * 
 * Author: Mladen Mijatov
 */

function onYouTubePlayerReady(playerId) {
	YouTube_Controls.init(playerId);
}


YouTube_Controls = {
	player: null,
	playlist: [],
	
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
	 * TODO: Finish YouTube embed player 
	 */
	addToPlaylist: function(video_id) {
		this.playlist.push(video_id);
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
};
