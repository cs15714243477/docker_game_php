layui.use('laydate',function(){
    var laydate = layui.laydate;
    //console.log(5555);
    if (dateOption.date1 !== undefined) {
        let initValue = $("#"+dateOption.date1+"").val();
        if (initValue == '') {
            initValue = new Date();
            //var date7 = new Date(initValue.getTime() - 30*24*60*60*1000); //前30天
            initValue = dateFor(initValue);
            initValue = initValue + " 00:00:00";
            $("#"+dateOption.date1+"").val(initValue);
        }

        laydate.render({
            elem: '#'+dateOption.date1
            , type: 'datetime'
            , format: 'yyyy-MM-dd HH:mm:ss'
            //, min: 'laydate.now()'
            //, max: +365
            , value: initValue
            , done: function (value) {
                // let date1 = new Date(value).getTime();
                // let date2 = new Date($('#'+dateOption.date2+'').val()).getTime();
                // if (date2 < date1) {
                //     layer.msg('结束时间不能小于开始时间');
                //     return;
                // }
                table.reload(dateOption.table, {
                    page: {curr: 1}
                    , where: {"startDate": value, "endDate": $('#'+dateOption.date2+'').val()}
                });
            }
        });
    }
    if (dateOption.date2 !== undefined) {
        let initValue = $("#"+dateOption.date2+"").val();
        if (initValue == '') {
            initValue = new Date();
            //var date7 = new Date(initValue.getTime() - 30*24*60*60*1000); //前30天
            initValue = initValue.setDate(initValue.getDate()+1);
            initValue = new Date(initValue);
            initValue = dateFor(initValue);
            initValue = initValue + " 00:00:00";
            $("#"+dateOption.date2+"").val(initValue);
        }
        // let initValue = $("#date2").val();
        // if (initValue == '') initValue = new Date();
        // let time2 = dateFor(initValue);
        // $("#date2").val(time2);
        laydate.render({
            elem: '#'+dateOption.date2
            , type: 'datetime'
            , format: 'yyyy-MM-dd HH:mm:ss'
            //, min: 'laydate.now()'
            //, max: +365
            , value: initValue
            , done: function (value) {
                // let date2 = new Date(value).getTime();
                // let date1 = new Date($('#'+dateOption.date1+'').val()).getTime();
                // if (date2 < date1) {
                //     layer.msg('结束时间不能小于开始时间');
                //     return;
                // }
                table.reload(dateOption.table, {
                    page: {curr: 1}
                    , where: {"startDate":$('#'+dateOption.date1+'').val(), "endDate": value}
                });
            }

        });
    }
})

function dateFor(date){
    let Y,M,D;
    Y = date.getFullYear() + '-';
    M = (date.getMonth()+1 < 10 ? '0'+(date.getMonth()+1) : date.getMonth()+1) + '-';
    D = date.getDate();
    return Y+M+D;
}