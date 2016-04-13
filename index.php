<?php

require "Resolver.php";

$resolver = null;
$result = '';

if (isset($_POST['tweet'])) {
	$resolver = new Resolver();
	$resolver->process($_POST['tweet']);
	$result = implode('', array_map(function (Token $t) {
		return $t->str;
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
</head>
<body>

<div class="OuterWrap">
	<div class="Container">
		<h1>Tweet Compressor</h1>

		<div class="Box input">
			<form method="POST" id="tweet-form">
				<label class="Label" for="tweet">Enter Your Tweet <span class="help">Submit with Enter</span></label>
				<textarea name="tweet" id="tweet" rows=5><?= isset($_POST['tweet']) ? e($_POST['tweet']) : '' ?></textarea>
			</form>
		</div>

		<?php if ($resolver): ?>

			<div class="Box output">
				<div class="Label">Tweaks</div>
				<div id="selection">
					<?php
					foreach ($resolver->tokens as $i => $tok) {
						if ($tok instanceof WordToken) {
							if(count($tok->options) > 1) {
								echo "<select id='w-$i'>";
								foreach ($tok->options as $opt) {
									$eo = e($opt);
									echo "<option value='$eo'>$eo</option>";
								}
								echo "</select>";
							} else {
								echo e($tok->str);
							}
						} else {
							echo e($tok->str);
						}
					}
					?>
				</div> <!-- shortening offers -->
			</div>

			<div class="Box output">
				<div class="Label">Result</div>
				<textarea name="result" id="result" rows=5><?= e($result) ?></textarea> <!-- actual result -->
				<div class="Length"><?= mb_strlen($result) . '/140' ?></div>
			</div>

		<?php endif; ?>
	</div>
</div>
</body>
</html>
