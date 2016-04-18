var twitmin = (function() {
	var tm = {};

	function updateCharCount() {
		var total = tm.totalLen;
		var len = 0;

		tm.fulltext = '';

		tm.tokens.forEach(function(t) {
			if (t.type == 'word') {
				var n = _.isUndefined(t.altN)?0:t.altN;
				len += t.options[n].length;
				tm.fulltext += t.options[n];
			} else if (t.type == 'special') {
				len += t.length; // special length for links and such
				tm.fulltext += t.str;
			} else {
				len += t.str.length;
				tm.fulltext += t.str;
			}
		});

		$('#disp-len').text(140-len).toggleClass('over', len>140);
		$('#disp-abs').text(total-len);
		$('#disp-perc').text(Math.round(((total-len)/total)*1000)/10);
	}

	function copyToClipboard(string) {
		// create hidden text element, if it doesn't already exist
		var targetId = "_hiddenCopyText_";
		var origSelectionStart, origSelectionEnd;

		// must use a temporary form element for the selection and copy
		target = document.getElementById(targetId);
		if (!target) {
			var target = document.createElement("textarea");
			target.style.position = "absolute";
			target.style.left = "-9999px";
			target.style.top = "0";
			target.id = targetId;
			document.body.appendChild(target);
		}
		target.textContent = string;

		// select the content
		var currentFocus = document.activeElement;
		target.focus();
		target.setSelectionRange(0, target.value.length);

		// copy the selection
		var succeed;
		try {
			succeed = document.execCommand("copy");
		} catch(e) {
			succeed = false;
		}
		// restore original focus
		if (currentFocus && typeof currentFocus.focus === "function") {
			currentFocus.focus();
		}

		// clear temporary content
		target.textContent = "";

		return succeed;
	}

	tm.init = function() {
		$(function () {
			function insertNewline(elem) {
				var $elem = $(elem);

				// insert newline at cursor
				var caretPos = elem.selectionStart;
				var str = $elem.val();
				$elem.val(str.substring(0, caretPos) + '\n' + str.substring(caretPos));

				// restore cursor
				elem.selectionStart = caretPos + 1;
				elem.selectionEnd = caretPos + 1;
			}

			function submitForm() {
				// submit the form
				$('#tweet-form').submit();
			}

			// enter / ctrl-enter handling
			$('#tweet').on('keyup', function (e) {
				if((e.keyCode == 10 || e.keyCode == 13) && (e.ctrlKey || e.metaKey)) {
					insertNewline(this);
				}
			}).on('keydown', function (e) {
				if((e.keyCode == 10 || e.keyCode == 13) && !(e.ctrlKey || e.metaKey)) {
					submitForm();
					e.preventDefault();
					e.stopImmediatePropagation();
					return false;
				}
			});

			// alt magic
			function showAltsFor(word) {
				$('.WordAlts').addClass('hidden');
				$(word).find('.WordAlts').removeClass('hidden');
			}

			$('.WordAlts').on('mouseenter', function() {
				clearTimeout($(this).closest('.Word').data('altTmeoHide'));
				clearTimeout($(this).closest('.Word').data('altTmeoHide2'));
			}).on('mouseleave', function() {
				var self = this;
				var id = setTimeout(function(){
					$(self).addClass('hidden');
				}, 500);
				$(this).closest('.Word').data('altTmeoHide2', id);
			});

			$('.Word').on('mouseenter', function() {
				var self = this;
				var $alts = $(self).find('.WordAlts');

				clearTimeout($(this).data('altTmeoHide'));
				clearTimeout($(this).data('altTmeoHide2'));

				if ($alts.hasClass('hidden')) {
					var id = setTimeout(function () {
						showAltsFor(self);
					}, 250);
					$(this).data('altTmeoShow', id);
				}
			}).on('mouseleave', function() {
				clearTimeout($(this).data('altTmeoShow'));
				var self = this;
				var $alts = $(self).find('.WordAlts');
				if (!$alts.hasClass('hidden')) {
					var id = setTimeout(function () {
						$alts.addClass('hidden')
					}, 500);
					$(this).data('altTmeoHide', id);
				}
			});

			$('.WordAlt').on('click', function() {
				var $w = $(this).closest('.Word');
				var altN = $(this).data('alt_n');
				var tokN = $w.data('tok_n');

				$w.find('.WVal').text(tm.tokens[tokN].options[altN]);

				// preserve the value
				tm.tokens[tokN].altN = altN;

				$('.WordAlts').addClass('hidden');

				updateCharCount();
			});

			$('#compress-btn').on('click', function() {
				submitForm();
			});

			$('#copy-btn').on('click', function() {
				copyToClipboard(tm.fulltext);
			});

			$('#tweet-btn').on('click', function() {
				location.href = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(tm.fulltext);
			});

			// update counter
			if (twitmin.tokens) {
				updateCharCount();
			}
		});
	};

	return tm;
})();

