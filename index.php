<?php

require "Resolver.php";

$resolver = null;
$result = '';
$original = '';

if (isset($_POST['tweet']) && $_POST['tweet'] !== '') {
	$resolver = new Resolver();
	$resolver->process($_POST['tweet']);

	$result = implode('', array_map(function (Token $t) {
		return ($t instanceof WordToken) ? $t->options[0] : $t->str;
	}, $resolver->tokens));
}

function e($s)
{
	return htmlspecialchars($s, ENT_HTML5 | ENT_QUOTES);
}

?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Tweet Compressor</title>
	<link rel="stylesheet" href="css/app.css">
	<script src="js/libs.js"></script>
	<script src="js/app.js"></script>

	<!--
	Contribute on GitHub!
	github.com/MightyPork/twitmin
	-->
</head>
<body>

<div class="OuterWrap">
	<div class="Container">
		<h1>Tweet Compressor</h1>

		<div class="Box input">
			<form method="POST" id="tweet-form">
				<label class="Label" for="tweet">Enter Your Tweet <span class="lbl-right">Submit with Enter</span></label>
				<textarea name="tweet" id="tweet" rows=10><?= isset($_POST['tweet']) ? e($_POST['tweet']) : '' ?></textarea>
			</form>
		</div>

		<?php if ($resolver): ?>

			<div class="Box output">
				<div class="Label">Output  <span class="lbl-right"><span class="btn" id="tweet-btn">TWEET</span> | <span class="btn" id="copy-btn">COPY</span></div>
				<div class="Output">
					<?php
					foreach ($resolver->tokens as $i => $tok) {
						if ($tok instanceof WordToken) {
							if(count($tok->options) > 1) {
								echo "<span class='Word' data-tok_n=$i>";
								echo "<span class='WVal'>".e($tok->options[0])."</span>";

								echo "<span class='WordAlts hidden'>";
								foreach ($tok->options as $j => $option) {
									$eo = e($tok->options[$j]);
									echo "<span class='WordAlt' data-alt_n='$j'>$eo</span>";
								}
								echo "</span>";//alts
								echo "</span>";
							} else {
								echo nl2br(e($tok->str));
							}
						} elseif ($tok instanceof SpecialToken) {
							switch ($tok->kind) {
								case 'url':
									echo "<a href='".e($tok->str)."' class='SpecialToken url'>".e($tok->str)."</a>";
									//e(mb_substr($tok->str, 0, 22))
									break;

								case 'hash':
									echo "<a href='https://twitter.com/hashtag/".e(mb_substr($tok->str, 1))."' class='SpecialToken hash'>".e($tok->str)."</a>";
									break;

								case 'handle':
									echo "<a href='https://twitter.com/".e(mb_substr($tok->str, 1))."' class='SpecialToken handle'>".e($tok->str)."</a>";
									break;

								default:
									echo "<span class='SpecialToken $tok->kind'>" . e($tok->str) . "</span>";
							}
						} else {
							echo nl2br(e($tok->str));
						}
					}
					?>
				</div>
				<div class="Length">
					<span id="disp-len"></span>

					<span id="rightside-leninfo">(removed <span id="disp-abs"></span> chars - <span id="disp-perc"></span> %)</span>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<script>
	<?php if ($resolver): ?>
	twitmin.totalLen = <?= $resolver->totalLength ?>;
	twitmin.tokens = <?= json_encode($resolver->tokens) ?>;
	<?php endif; ?>

	twitmin.init();
</script>
</body>
</html>
