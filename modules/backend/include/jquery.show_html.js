 //Animates the dimensional changes resulting from altering element contents
 //Usage examples:
	 //$("#myElement").showHtml("new HTML contents");
	 //$("div").showHtml("new HTML contents", 400);
	 //$(".className").showHtml("new HTML contents", 400,
						  //function() {/* on completion */});
(function($) {
	$.fn.setWindowContent = function(html, speed, callback) {
		return this.each(function() {
			//The element to be modified
			var el = $(this);
			var content = $(this._content);

			//Preserve the original values of width and height - they'll need
			//to be modified during the animation, but can be restored once
			//the animation has completed.
			var finish = {
					width: this.style.width,
					height: this.style.height
				};

			//The original width and height represented as pixel values.
			//These will only be the same as `finish` if this element had its
			//dimensions specified explicitly and in pixels. Of course, if that
			//was done then this entire routine is pointless, as the dimensions
			//won't change when the content is changed.
			var cur = {
					top: el.position().top,
					left: el.position().left,
					width: el.width(),
					height: el.height()
				};

			//Modify the element's contents. Element will resize.
			content.html(html);

			//Capture the final dimensions of the element
			//(with initial style settings still in effect)
			var next = {
					top: cur.top + Math.floor((cur.height - el.height()) / 2),
					left: cur.left + Math.floor((cur.width - el.width()) / 2),
					width: el.width(),
					height: el.height()
				};

			el.css(cur) // restore initial dimensions
				.animate(next, speed, function()  // animate to final dimensions
				{
					el.css(finish); // restore initial style settings
					if ( $.isFunction(callback) ) callback();
				});
		});
	};


})(jQuery);

