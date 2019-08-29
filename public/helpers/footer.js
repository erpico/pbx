var result;
webix.ui.datafilter.avgColumn = webix.extend({
	refresh:function(master, node, value){
		result = 0;
		master.mapCells(null, value.columnId, null, 1, function(value){
			result++;
			return value;
		});
		node.firstChild.innerHTML = result;
	}
}, webix.ui.datafilter.summColumn);

webix.ui.datafilter.minColumn = webix.extend({
    refresh:function(master, node, value){ 
        var result = 0;
        master.mapCells(null, value.columnId, null, 1, function(value){
            value = value*1;
            if (!isNaN(value))
                result+=value;
            return value;
        });
 
        node.firstChild.innerHTML = result.toFixed(2);
    }
}, webix.ui.datafilter.summColumn);

webix.ui.datafilter.avaregeColumn = webix.extend({
    refresh:function(master, node, value){ 
        var result = 0;
		var i = 0;
        master.mapCells(null, value.columnId, null, 1, function(value){
            value = value*1;
            if (!isNaN(value)) {
                result+=value;
				/*if(value!=0)*/ i++;
			};
            return value;
        });
 
        node.firstChild.innerHTML = (result/i).toFixed(2);
    }
}, webix.ui.datafilter.summColumn);

webix.ui.datafilter.summTimeColumn = webix.extend({
    refresh:function(master, node, value){ 
        var day = 0;
        var hours = 0;
		var hours_i = 0;
		var minutes = 0;
		var minutes_i = 0;
		var seconds = 0;
		var seconds_i = 0;
		var datetime_arr = new Array();
		var day_arr = new Array();
		var time_arr = new Array();
        master.mapCells(null, value.columnId, null, 1, function(value){
            if (value) {// 1d 12:34:04
				/*time_arr = value.split(':');
				minutes+=parseInt(time_arr[0], 10);
				seconds+=parseInt(time_arr[1], 10);*/
				datetime_arr = value.split(' ');
				if(datetime_arr.length==2) {
					day_arr = datetime_arr[0].split(translate('d'));
					day+=parseInt(day_arr[0], 10);
					time_arr = datetime_arr[1].split(':');
					hours+=parseInt(time_arr[0], 10);
					minutes+=parseInt(time_arr[1], 10);
					seconds+=parseInt(time_arr[2], 10);
				}
				else{
					time_arr = datetime_arr[0].split(':');
					if(time_arr.length==3) {
						hours+=parseInt(time_arr[0], 10);
						minutes+=parseInt(time_arr[1], 10);
						seconds+=parseInt(time_arr[2], 10);
					}
					else {
						minutes+=parseInt(time_arr[0], 10);
						seconds+=parseInt(time_arr[1], 10);
					};
				};
			};
            return value;
        });
		while(seconds>=60) {
			seconds = seconds - 60;
			seconds_i++;
		};
		minutes = minutes+seconds_i;
		while(minutes>=60) {
			minutes = minutes - 60;
			minutes_i++;
		};
		hours = hours + minutes_i;
		while(hours>=24) {
			hours = hours - 24;
			hours_i++;
		};
		day = day + hours_i;
		if(seconds<10) seconds = "0"+seconds;
		if(minutes<10) minutes = "0"+minutes;
		var time = "";//day+" Days "+hours+":"+minutes+":"+seconds;
		if(day>0) time = time+day+translate("d")+" ";
		if(hours>0) time = time+hours+":"; else if(day>0) time = time+"0:"
		if(minutes>0) time = time+minutes+":"; else time = time+"00:";
		if(seconds>0) time = time+seconds; else time = time+"00";
        node.firstChild.innerHTML = time;
    }
}, webix.ui.datafilter.summColumn);

webix.ui.datafilter.avaregeTimeColumn = webix.extend({
    refresh:function(master, node, value){
        var hours = 0;
		var minutes = 0;
		var minutes_i = 0;
		var seconds = 0;
		var seconds_i = 0;
		var count = 0;
		var time_arr = new Array();
        master.mapCells(null, value.columnId, null, 1, function(value){
            if (value) {
				time_arr = value.split(':');
				minutes+=parseInt(time_arr[0], 10);
				seconds+=parseInt(time_arr[1], 10);
				count++;
			};
            return value;
        });
		
		var total_seconds = minutes*60+seconds;
		seconds = total_seconds/count;
		seconds = Math.round(seconds);
		
		while(seconds>=60) {
			seconds = seconds - 60;
			seconds_i++;
		};
		minutes = seconds_i;
		while(minutes>=60) {
			minutes = minutes - 60;
			minutes_i++;
		};
		hours = hours + minutes_i;
		if(seconds<10) seconds = "0"+seconds;
		if(minutes<10) minutes = "0"+minutes;
		var time = hours+":";
		if(minutes>0) time = time+minutes+":"; else time = time+"00:";
		if(seconds>0) time = time+seconds; else time = time+"00";
        node.firstChild.innerHTML = time;
    }
}, webix.ui.datafilter.summColumn);