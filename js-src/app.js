$(function () {
	$('#tweet').on('keyup', function (e) {
		if((e.keyCode == 10 || e.keyCode == 13) && (e.ctrlKey || e.metaKey)) {
			var $th = $(this);
			var elem = this;

			// insert newline at cursor
			var caretPos = elem.selectionStart;
			var str = $th.val();
			$th.val(str.substring(0, caretPos) + '\n' + str.substring(caretPos));

			// restore cursor
			elem.selectionStart = caretPos + 1;
			elem.selectionEnd = caretPos + 1;
		}
	}).on('keydown', function (e) {
		if((e.keyCode == 10 || e.keyCode == 13) && !(e.ctrlKey || e.metaKey)) {
			// submit the form
			$('#tweet-form').submit();
			e.preventDefault();
			e.stopImmediatePropagation();
			return false;
		}
	});
});
