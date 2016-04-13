<?php

require "Resolver.php";

$resolver = null;
$result = '';
$original = '';

if (isset($_POST['tweet'])) {
	$original = $_POST['tweet'];
	$resolver = new Resolver();
	$resolver->process($original);

	$result = implode('', array_map(function (Token $t) {
		return ($t instanceof WordToken) ? $t->options[0] : $t->str;
	}, $resolver->tokens));

	$origlen = mb_strlen($original);
	if($origlen!=0) {
		$reduction = number_format(((mb_strlen($original) - mb_strlen($result)) / mb_strlen($original)) * 100, 1, '.', '');
	} else {
		$reduction = '0.0';
	}
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
				<div class="Label">Output  <span class="lbl-right" id="copy-btn">COPY</span></div>
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
								echo e($tok->str);
							}
						} else {
							echo e($tok->str);
						}
					}
					?>
				</div>
				<div class="Length">
					<span id="disp-len"></span>/140

					<span id="rightside-leninfo">(removed <span id="disp-abs"></span> chars - <span id="disp-perc"></span> %)</span>
				</div>
			</div>

			<script>
				twitmin.totalLen = <?= mb_strlen($original) ?>;
				twitmin.tokens = <?= json_encode($resolver->tokens) ?>;

				twitmin.init();
			</script>
		<?php endif; ?>
	</div>
</div>
</body>
</html>
