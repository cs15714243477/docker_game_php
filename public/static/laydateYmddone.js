layui.use('laydate',function(){
    var laydate = layui.laydate;
    if (dateOption.date1 !== undefined) {
        let initValue = new Date();
        //if (dateOption.date1 != '') initValue = $('#'+dateOption.date1).val();
        laydate.render({
            elem: '#'+dateOption.date1
            , type: 'date'
            , format: 'yyyy-MM-dd'
            //, min: 'laydate.now()'
            , max: 'laydate.now()'
            , value: initValue
            , done: function (value) {
                let date1 = new Date(value).getTime();
                let date2 = new Date($('#'+dateOption.date2).val()).getTime();
                if (date2 < date1) {
                    layer.msg('结束时间不能小于开始时间');
                    return;
                }
                table.reload(dateOption.tableId, {
                    page: {curr: 1}
                    , where: {"startDate": value, "endDate": $('#'+dateOption.date2).val()}
                });
            }
        });
    }
    if (dateOption.date2 !== undefined) {
        let initValue = new Date();
        //if (dateOption.date2 != '') initValue = $('#'+dateOption.date2).val();
        laydate.render({
            elem: '#'+dateOption.date2
            , type: 'date'
            , format: 'yyyy-MM-dd'
            //, min: 'laydate.now()'
            //, max: +365
            , value: initValue
            , done: function (value) {
                let date2 = new Date(value).getTime();
                let date1 = new Date($('#'+dateOption.date1).val()).getTime();
                if (date2 < date1) {
                    layer.msg('结束时间不能小于开始时间');
                    return;
                }
                table.reload(dateOption.tableId, {
                    page: {curr: 1}
                    , where: {"startDate": $('#'+dateOption.date1).val(), "endDate": value}
                });
            }
        });
    }
})