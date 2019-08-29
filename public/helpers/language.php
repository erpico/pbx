<?
if(isset($_COOKIE['language'])) $library = "i18n/".str_replace('"', "", $_COOKIE['language']).".php";
else $library = "i18n/en.php";

include($library);

$key = array_keys($translation_table);
$key_count = count($key);

for($i=0; $i<$key_count; $i++) {
	$text = $key[$i];
	$translation_table[$text] = (isset($translation_table[$text]) && $translation_table[$text]!="") ? $translation_table[$text] : $text;
};
echo json_encode($translation_table);
?>