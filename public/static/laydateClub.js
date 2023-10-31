layui.use('laydate',function(){
    var laydate = layui.laydate;
    if (dateOption.date1 !== undefined) {
        let initValue = $("#date1").val();
        if (initValue == '') {
            initValue = new Date();
            //var date7 = new Date(initValue.getTime() - 30*24*60*60*1000); //前30天
            let time1 = dateFor(initValue);
            $("#date1").val(time1);
        }

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
        if (initValue == '') {
            initValue = new Date();
            //var date7 = new Date(initValue.getTime() - 30*24*60*60*1000); //前30天
            let time2 = dateFor(initValue);
            $("#date2").val(time2);
        }
        // let initValue = $("#date2").val();
        // if (initValue == '') initValue = new Date();
        // let time2 = dateFor(initValue);
        // $("#date2").val(time2);
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

function dateFor(date){
    let Y,M,D;
    Y = date.getFullYear() + '-';
    M = (date.getMonth()+1 < 10 ? '0'+(date.getMonth()+1) : date.getMonth()+1) + '-';
    D = date.getDate();
    return Y+M+D;
}