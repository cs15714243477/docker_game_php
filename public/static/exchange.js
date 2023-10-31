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

window.qrcodeShow = function (obj, option) {
    let defaultOption = {"tableId": 'table1',"width": '500px', "height": '400px'};
    $.extend(defaultOption, option);
    layer.open({
        type: 1,
        title: '会员Id:<span style="color: red">' + obj.data['userId'] + '</span> ,昵称:' + obj.data['nickName'] + ' 的收款码',
        maxmin: true,
        shadeClose: true, //点击遮罩关闭层
        shade: 0.1,
        area: [defaultOption.width, defaultOption.height],
        content: '<div style="line-height: 30px;margin-left: 10px"><p>订单号：'+ obj.data['orderId'] +'</p>\n' +
            '<p>提款账号：<span style="background-color: #00FF00">USDT-'+ obj.data['usdtTypeCn'] +'</span>  '+ obj.data['usdtAddress'] +'</p>\n' +
            '<p><span>提款金额：<span style="color: red">'+ obj.data['usdt'] +' USDT</span></span>&nbsp;&nbsp;&nbsp;&nbsp;<span>汇率：<span style="color: blue">'+ obj.data['usdtRate'] +'</span></span>&nbsp;&nbsp;&nbsp;&nbsp;<span>提款状态：'+ obj.data['statusCn'] +'</span></p>\n' +
            '<p id="qrcodeShow" style="text-align:center;margin-top: 30px"></p></div>',
        success: function(layero, index) {
            $('#qrcodeShow').qrcode({
                render: "canvas",  //table canvas
                width: 100,
                height: 100,
                //foreground: "#C00",
                //background: "#FFF",
                text: obj.data['usdtAddress']
            });
        }
    });
}

window.auditOrder = function(obj, option) {
    let defaultOption = {"tableId": 'table1'};
    $.extend(defaultOption, option);
    $.ajax({
        url: defaultOption.submitUrl,
        data: {"_id": obj.data['_id']},
        type: 'post',
        success: function(res){
            if(res.code == 0){
                layer.msg(res.msg,{icon:1,time:1000},function(){
                    //parent.layer.close(parent.layer.getFrameIndex(window.name));//关闭当前页
                    //parent.layui.table.reload(defaultOption.tableId);
                    obj.tr.find('[lay-event="audit"]').html('<button class="layui-btn layui-btn-xs layui-btn-disabled">审核</button>');
                    obj.tr.find('[lay-event="reject"]').html('<button class="layui-btn layui-btn-xs layui-btn-disabled">驳回</button>');
                });
            }else{
                layer.msg(res.msg,{icon:2,time:1000});
            }
        }
    })
}

window.rejectOrder = function(obj, option){
    let defaultOption = {"width": '500px', "height": '400px', "title": '新增'};
    $.extend(defaultOption, option);
    layer.open({
        type: 2,
        title: defaultOption.title,
        maxmin: true,
        shadeClose: true, //点击遮罩关闭层
        shade: 0.1,
        area: [defaultOption.width, defaultOption.height],
        content: defaultOption.submitUrl,
        btn: ['确定', '取消'],
        yes: function (index, layero) {
            let body = layer.getChildFrame('body', index);
            let info = body.find('#rejececontent').val();
            $.ajax({
                url: defaultOption.submitUrl,
                data: {"_id": obj.data['_id'],"userId": obj.data['userId'], "orderId": obj.data['orderId'], "requestMoney": obj.data['requestMoney']*100, "reason": info},
                type: 'post',
                success: function (res) {
                    layer.msg(res.msg, {
                        offset: '15px'
                        , icon: 1
                        , time: 1000
                    }, function () {
                        if (res.code == 0) {
                            obj.tr.find('[lay-event="actionAll"]').html('<span class="layui-btn layui-btn-xs layui-btn-danger" style="width: 140px;">该笔订单《驳回申请》</span>');
                        }
                    });
                }
            });
            layer.close(index);
        }
    });
}

window.withdrawUsdt = function(obj, option){
    let defaultOption = {"tableId": 'table1'};
    $.extend(defaultOption, option);
    layer.confirm('该订单是否已汇款？', {icon: 3, title:'提示', btn: ['已汇款', '未汇款']}, function(index){
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
                        obj.tr.find('[lay-event="actionAll"]').html('<span class="layui-btn layui-btn-xs" style="width: 140px;">该笔订单《汇款成功》</span>');
                        obj.update({
                            exchangeAmount: Number(obj.data['exchangeAmount']) + Number(obj.data['requestMoney'])
                        });
                    });
                }else{
                    layer.msg(res.msg,{icon:2,time:1000});
                }
            }
        })
    });
}

window.withdrawBank = function (obj, option) {
    let defaultOption = {"tableId": 'table1'};
    $.extend(defaultOption, option);
    $.ajax({
        url: defaultOption.submitUrl3,
        type: 'post',
        success:function(res){
            let s_option = '';
            let tempData = res.data;
            let len = tempData.length;
            let is_open = 0;
            if (len > 0) {
                is_open = 1;
            }
            if (len >= 1) {
                s_option = '<select name="zfbdf" id="zfbdf"><option value="">请选择银行卡支付渠道</option>';
                for(let i = 0; i < len; i++) {
                    s_option += '<option value="'+ tempData[i]['exchangeServiceId'] +'">' + tempData[i]['controllerName'] + '</option>';
                }
                s_option += '</select>';
            }

            layer.confirm('该订单是否通过网银汇款？'+ s_option, {icon: 3, title:'提示',area: ['300px', '300px'], btn: ['手动汇款', '在线网银']}, function(index){
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
                                obj.tr.find('[lay-event="actionAll"]').html('<span class="layui-btn layui-btn-xs" style="width: 140px;">该笔订单《汇款成功》</span>');
                                obj.update({
                                    exchangeAmount: Number(obj.data['exchangeAmount']) + Number(obj.data['requestMoney'])
                                });
                            });
                        }else{
                            layer.msg(res.msg,{icon:2,time:1000});
                        }
                    }
                })
            }, function (index){
                layer.close(index);
                if (!is_open) {
                    layer.msg('没有可用的银行卡支付渠道', {offset: '15px', icon: 0, time: 3000});
                    return false;
                }

                let options = $("#zfbdf option:selected");
                let exchangeServiceId = 0;
                if (options.val() != undefined) {
                    exchangeServiceId = options.val();
                }

                if(exchangeServiceId <= 0){
                    layer.msg('请选择的银行卡支付渠道', {offset: '15px', icon: 0, time: 3000});
                    return false;
                }

                let loading = layer.load();

                $.ajax({
                    url: defaultOption.submitUrl2,
                    data: {"_id": obj.data['_id'],"exchangeServiceId": exchangeServiceId},
                    type: 'post',
                    success:function(res){
                        layer.close(loading);
                        if(res.code == 0){
                            layer.msg(res.msg,{icon:1,time:1000},function(){
                                obj.tr.find('[lay-event="actionAll"]').html('<span class="layui-btn layui-btn-xs" style="width: 140px;">该笔订单《汇款中》</span>');
                            });
                        }else{
                            layer.msg(res.msg,{icon:2,time:1000});
                        }
                    }
                })
            });
        }
    });
}

window.withdrawAlipay = function (obj, option) {
    let defaultOption = {"tableId": 'table1'};
    $.extend(defaultOption, option);
    $.ajax({
        url: defaultOption.submitUrl3,
        type: 'post',
        success:function(res){
            let s_option = '';
            let tempData = res.data;
            let len = tempData.length;
            let is_open = 0;
            if (len > 0) {
                is_open = 1;
            }
            if (len >= 1) {
                s_option = '<select name="zfbdf" id="zfbdf"><option value="">请选择支付宝代付账号</option>';
                for(let i = 0; i < len; i++) {
                    s_option += '<option value="'+ tempData[i]['exchangeServiceId'] +'">' + tempData[i]['controllerName'] + '</option>';
                }
                s_option += '</select>';
            }

            layer.confirm('该订单是否通过支付宝汇款？' + s_option, {icon: 3, title:'提示',area: ['300px', '300px'], btn: ['手动汇款', '支付宝']}, function(index){
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
                                obj.tr.find('[lay-event="actionAll"]').html('<span class="layui-btn layui-btn-xs" style="width: 140px;">该笔订单《汇款成功》</span>');
                                obj.update({
                                    exchangeAmount: Number(obj.data['exchangeAmount']) + Number(obj.data['requestMoney'])
                                });
                            });
                        }else{
                            layer.msg(res.msg,{icon:2,time:1000});
                        }
                    }
                })
            }, function (index){
                layer.close(index);
                if (!is_open) {
                    layer.msg('没有可用的支付宝代付', {offset: '15px', icon: 0, time: 3000});
                    return false;
                }
                let loading = layer.load();
                let options = $("#zfbdf option:selected");
                let exchangeServiceId = 0;
                if (options.val() != undefined) {
                    exchangeServiceId = options.val();
                }
                $.ajax({
                    url: defaultOption.submitUrl2,
                    data: {"_id": obj.data['_id'], "exchangeServiceId": exchangeServiceId},
                    type: 'post',
                    success:function(res){
                        layer.close(loading);
                        if(res.code == 0){
                            layer.msg(res.msg,{icon:1,time:1000},function(){
                                obj.tr.find('[lay-event="actionAll"]').html('<span class="layui-btn layui-btn-xs" style="width: 140px;">该笔订单《汇款中》</span>');
                            });
                        }else{
                            layer.msg(res.msg,{icon:2,time:1000});
                        }
                    }
                })
            });

        }
    })
}
