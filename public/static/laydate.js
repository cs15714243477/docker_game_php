layui.use('laydate',function(){
    var laydate = layui.laydate;
    if (dateOption.date1 !== undefined) {
        let initValue = new Date();
        if (dateOption.date1 != '') initValue = dateOption.date1;
        laydate.render({
            elem: '#date1'
            , type: 'datetime'
            , format: 'yyyy-MM-dd HH:mm:ss'
            , min: 'laydate.now()'
            , max: +365
            , value: initValue
        });
    }
    if (dateOption.date2 !== undefined) {
        let initValue = new Date();
        if (dateOption.date2 != '') initValue = dateOption.date2;
        laydate.render({
            elem: '#date2'
            , type: 'datetime'
            , format: 'yyyy-MM-dd HH:mm:ss'
            , min: 'laydate.now()'
            , max: +365
            , value: initValue
        });
    }
})