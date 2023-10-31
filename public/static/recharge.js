window.updateOrderStatus = function(obj, option){
    let defaultOption = {"tableId": 'table1'};
    $.extend(defaultOption, option);
    layer.confirm('确认要订单已支付吗', {icon: 3, title:'提示'}, function(index){
        layer.close(index);
        let loading = layer.load();
        $.ajax({
            url: defaultOption.submitUrl,
            data: {"_id": obj.data['_id']},
            type: 'post',
            success:function(res){
                layer.close(loading);
                if(res.code == 0){
                    layer.msg(res.msg,{icon:1,time:1000},function(){
                        obj.tr.find('[lay-event="updateStatus"]').parent().html('<span class="layui-btn layui-btn-xs layui-btn-normal">已支付</span>');
                    });
                }else{
                    layer.msg(res.msg,{icon:2,time:1000});
                }
            }
        })
    });
}

window.cancleOrder = function(obj, option){
    let defaultOption = {"tableId": 'table1'};
    $.extend(defaultOption, option);
    layer.confirm('确认要取消订单吗？', {icon: 3, title:'提示'}, function(index){
        layer.close(index);
        let loading = layer.load();
        $.ajax({
            url: option.submitUrl,
            data: {"_id": obj.data['_id']},
            type: 'post',
            success:function(res){
                layer.close(loading);
                if(res.code == 0){
                    layer.msg(res.msg,{icon:1,time:1000},function(){
                        obj.tr.find('[lay-event="cancle"]').parent().html('<span class="layui-btn layui-btn-xs layui-btn-danger">已取消</span>');
                    });
                }else{
                    layer.msg(res.msg,{icon:2,time:1000});
                }
            }
        })
    });
}

window.tableSummary = function(option) {
    let defaultOption = {"tableId": 'table1'};
    $.extend(defaultOption, option);
    let selIdArr = defaultOption.selIds.split(",")
    selIdArr.map(function (item, index, arr){
        $("#"+item).text(0);
    })
    $.ajax({
        url: defaultOption.submitUrl,
        data: defaultOption.data,
        type: 'post',
        success: function(res){
            if(res.code == 0){
                selIdArr.map(function (item, index, arr){
                    $("#"+item).text(res.data[item]);
                    /*count.up(item, {
                        time: 8000,
                        num: res.data[item],
                        bit: 2,
                        regulator: 100
                    })*/
                })
            }else{

            }
        }
    })
}
