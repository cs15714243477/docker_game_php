layui.use('laydate',function(){
    var laydate = layui.laydate;
    if (dateOption.date1 !== undefined) {
        let initValue = $("#date1").val();
        if (initValue == '') initValue = new Date();

        laydate.render({
            elem: '#date1'
            , type: 'date'
            , format: 'yyyy-MM-dd'
            //, min: 'laydate.now()'
            //, max: +365
            , value: initValue
        });
    }
    if (dateOption.date2 !== undefined) {
        let initValue = $("#date2").val();
        if (initValue == '') initValue = new Date();
        laydate.render({
            elem: '#date2'
            , type: 'date'
            , format: 'yyyy-MM-dd'
            //, min: 'laydate.now()'
            //, max: +365
            , value: initValue
        });
    }
})