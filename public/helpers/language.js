if(!webix.storage.cookie.get("language")) webix.storage.cookie.put("language", "ru");

var xhr = webix.ajax().sync().post("helpers/language.php");
var text = xhr.responseText;
translation_table = JSON.parse(text);

function translate(txt) {
	return text = (translation_table[txt]!== undefined && translation_table[txt]!="") ? translation_table[txt] : txt;
};

function dateSetHM(date,h,m) {	
	return new Date(date.getFullYear(), date.getMonth(), date.getDate(), h, m, 0);		
}