$.fn.extend({
	insertAtCaret: function(myValue){
		this.each(function(i) {
			if (document.selection) {
				this.focus();
				sel = document.selection.createRange();
				sel.text = myValue;
				this.focus();
			} else if (this.selectionStart || this.selectionStart == '0') {
				var startPos = this.selectionStart;
				var endPos = this.selectionEnd;
				var scrollTop = this.scrollTop;
				this.value = this.value.substring(0, startPos) + myValue + this.value.substring(endPos, this.value.length);
				this.focus();
				this.selectionStart = startPos + myValue.length;
				this.selectionEnd = startPos + myValue.length;
				this.scrollTop = scrollTop;
			} else {
				this.value += myValue;
				this.focus();
			}
		});
	}
});

$.fn.extend({
	replaceSelection: function(new_value, offset){
		this.each(function(i) {
			var sel_start = this.selectionStart;
			var sel_end = this.selectionEnd;
			var sel_offset = offset != undefined ? offset : 0;
			var scroll_top = this.scrollTop;
			var value = this.value;

			this.value = value.substring(0, sel_start) + new_value + value.substring(sel_end);
			this.focus();

			if (sel_start != sel_end) {
				// selection existed, restore it
				this.selectionStart = sel_start;
				this.selectionEnd = sel_start + new_value.length;

			} else {
				// no previous selection, set caret at offset
				if (offset != undefined) {
					this.selectionStart = sel_start + sel_offset;
					this.selectionEnd = sel_start + sel_offset;
				}
			}

			this.scrollTop = scroll_top;
			this.focus();
		});
	}
});

$.fn.selectRange = function(start, end) {
    return this.each(function() {
        if (this.setSelectionRange) {
            this.focus();
            this.setSelectionRange(start, end);
        } else if (this.createTextRange) {
            var range = this.createTextRange();
            range.collapse(true);
            range.moveEnd('character', end);
            range.moveStart('character', start);
            range.select();
        }
    });
};
