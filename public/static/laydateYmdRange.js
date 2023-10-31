function getDay(day){
    var today = new Date();
    var targetday_milliseconds=today.getTime() + 1000*60*60*24*day;
    today.setTime(targetday_milliseconds);
    var tYear = today.getFullYear();
    var tMonth = today.getMonth();
    var tDate = today.getDate();
    tMonth = doHandleMonth(tMonth + 1);
    tDate = doHandleMonth(tDate);
    return tYear+"-"+tMonth+"-"+tDate;
}
function doHandleMonth(month){
    var m = month;
    if(month.toString().length == 1){
        m = "0" + month;
    }
    return m;
}
layui.use('laydate',function(){
    let laydate = layui.laydate;
    let before7 = getDay(-7);
    let curentday = getDay(0);
    let datestr = before7+' ~ '+curentday;
    laydate.render({
        elem: '#'+dateOption.dateId,
        type:'date',
        range: '~',
        format: 'yyyy-MM-dd',
        min: -90,
        max: 'laydate.now()',
        value:datestr,
        ready: function (date) {
            console.log('ready');
        },
        change: function(dateValue){
            var d = dateValue.split(' ~ ');
            var s = new Date(d[0]);
            var e = new Date(d[1]);
            var d=(e-s)/(1000*60*60*24);
            if(d>90){
                alert('最多选择90天'); return false;
            }
        },
        done: function (v){
            dateOption.callback(0, v);
        }
    });
})