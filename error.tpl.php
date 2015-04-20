<?php
if (count($errors))
{
?>
<script type="text/javascript">
function showErrors(bringToFront)
{
    errorWindow = window.open('', 'errorConsole', 'resizable=yes,scrollbars=yes,directories=no,location=no,menubar=no,status=no,toolbar=no');
    errorWindow.document.open();
    errorWindow.document.writeln('<html><head><title>errorWindow Console: <?php echo $_SERVER['PHP_SELF']; ?></title>');
    errorWindow.document.writeln('<style>	body { font-family: sans-serif; font-size: 12px; background:#F7F7F3;}	span.errorLevel { font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: green; }	div.errorMessage { font-weight: bold; font-family: courier; color: #000; }	</style>');
    errorWindow.document.writeln('<script>function errorJump(index, offset) { if (element = document.getElementById(\'error\' + (index + offset))) { window.scrollTo(0, element.offsetTop); } return void(0); }</scr' + 'ipt>');
    errorWindow.document.writeln('</head><body>');
    <?php
    foreach ($errors as $index => $error) {
    ?>
    errorWindow.document.writeln('<div id="error<?php echo $index; ?>" style="padding-left:2px; padding-bottom:2px; background:#ddd; float: right;">(<?php echo $index; ?>)&nbsp; <a href="javascript: errorJump(<?php echo $index; ?>, -1);">prev</a> | <a href="javascript: errorJump(<?php echo $index; ?>, 1);">next</a></div><?php echo $error; ?><div style="height: 1px; line-height: 1px; background-color: black; border: 0; margin-bottom: 5px;"><br /></div>');
    <?php
    }
    ?>
    errorWindow.document.writeln('</body></html>');
    errorWindow.document.close();
    if (bringToFront) errorWindow.focus();
    return false;
}

showErrors();

</script>
<div style="position: absolute; right: 1px; top: 1px;"><img src="<?php


	// set the right path to the image
	// BE or FE
echo (preg_replace('#^'.$GLOBALS['_SERVER']["DOCUMENT_ROOT"].'#','',t3lib_extMgm::extPath('cc_debug')));


?>core.png" width="36" height="36" style="cursor: pointer; cursor: hand;" onclick="return showErrors(true);" /></div>
<?php
}
?>
