window.openNewTab = function(obj){
    let id = $(obj).attr('data-name');
    let title = $(obj).attr('lay-text');
    let url = $(obj).attr('lay-href');
    top.layui.tab.addTabOnlyByElem("content",{id:id,title:title,url:url,close:"允许关闭"})
}
window.add = function(url, option){
    let defaultOption = {"width": '500px', "height": '400px', "title": '新增'};
    $.extend(defaultOption, option);
    layer.open({
        type: 2,
        title: defaultOption.title,
        shade: 0.1,
        area: [defaultOption.width, defaultOption.height],
        content: url
    });
}

window.edit = function(url, option){
    let defaultOption = {"width": '500px', "height": '400px', "title": '修改'};
    $.extend(defaultOption, option);
    layer.open({
        type: 2,
        title: defaultOption.title,
        shade: 0.1,
        area: [defaultOption.width, defaultOption.height],
        content: url
    });
}

window.agentdetail = function(url, option){
    let defaultOption = {"width": '500px', "height": '400px', "title": '新增'};
    $.extend(defaultOption, option);
    layer.open({
        type: 2,
        title: '',
        shade: 0.1,
        area: [defaultOption.width, defaultOption.height],
        content: url
    });
}

window.viewReason = function(url, option){
    let defaultOption = {"width": '500px', "height": '400px', "title": '查看原因'};
    $.extend(defaultOption, option);
    layer.open({
        type: 2,
        title: '',
        shade: 0.1,
        area: [defaultOption.width, defaultOption.height],
        content: url
    });
}

window.refresh = function(table_id, option){
    if (option.data !== undefined) {
        layui.table.reload(table_id, {where:option.data.field, page: {curr: 1}});
    } else {
        layui.table.reload(table_id);
    }

}

window.power = function(url){
    layer.open({
        type: 2,
        title: '授权',
        shade: 0.1,
        area: ['320px', '400px'],
        content: url
    });
}

window.switch01 = function (obj, option) {
    let v = 0;
    if (obj.elem.checked === true) {
        v = 1;
    }
    $.ajax({
        url: option.submitUrl,
        data: {"_id": obj.value, "field": option.field, "value": v},
        type: 'post',
        success: function(res){
            if(res.code == 0){
                layer.msg(res.msg,{icon:1,time:2000},function(){
                    //parent.layer.close(parent.layer.getFrameIndex(window.name));//关闭当前页
                    //parent.layui.table.reload("table1");
                });
            }else{
                layer.msg(res.msg,{icon:2,time:1000});
            }
        }
    })
}
window.switch12 = function (obj, option) {
    let v = 1;
    if (obj.elem.checked === true) {
        v = 2;
    }
    $.ajax({
        url: option.submitUrl,
        data: {"_id": obj.value, "field": option.field, "value": v},
        type: 'post',
        success: function(res){
            if(res.code == 0){
                layer.msg(res.msg,{icon:1,time:2000},function(){
                    //parent.layer.close(parent.layer.getFrameIndex(window.name));//关闭当前页
                    //parent.layui.table.reload("table1");
                });
            }else{
                layer.msg(res.msg,{icon:2,time:1000});
            }
        }
    })
}

window.switch21 = function (obj, option) {
    let v = 2;
    if (obj.elem.checked === true) {
        v = 1;
    }
    $.ajax({
        url: option.submitUrl,
        data: {"_id": obj.value, "field": option.field, "value": v},
        type: 'post',
        success: function(res){
            if(res.code == 0){
                layer.msg(res.msg,{icon:1,time:2000},function(){
                    //parent.layer.close(parent.layer.getFrameIndex(window.name));//关闭当前页
                    //parent.layui.table.reload("table1");
                });
            }else{
                layer.msg(res.msg,{icon:2,time:1000});
            }
        }
    })
}

window.editTableCell = function (obj, option) {
    let defaultOption = {"tableId": 'table1'};
    $.extend(defaultOption, option);
    $.ajax({
        url: option.submitUrl,
        data: {"_id": obj.data._id, "field": obj.field, "value": obj.value},
        type: 'post',
        success: function(res){
            if(res.code == 0){
                layer.msg(res.msg,{icon:1,time:1000},function(){
                    //layer.close(parent.layer.getFrameIndex(window.name));//关闭当前页
                    layui.table.reload(defaultOption.tableId);
                });
            }else{
                layer.msg(res.msg,{icon:2,time:1000});
            }
        }
    })
}

window.editConPointSort = function (obj, option) {
    let defaultOption = {"tableId": 'table1'};
    $.extend(defaultOption, option);
    $.ajax({
        url: option.submitUrl,
        data: {"_id": obj.data.userId, "field": obj.field, "value": obj.value},
        type: 'post',
        success: function(res){
            if(res.code == 0){
                layer.msg(res.msg,{icon:1,time:1000},function(){
                    //layer.close(parent.layer.getFrameIndex(window.name));//关闭当前页
                    layui.table.reload(defaultOption.tableId);
                });
            }else{
                layer.msg(res.msg,{icon:2,time:1000});
            }
        }
    })
}

window.editTableNum = function (obj, option) {
    let defaultOption = {"tableId": 'table1'};
    $.extend(defaultOption, option);
    $.ajax({
        url: option.submitUrl,
        data: {"clubId": obj.data.clubId, "field": obj.field, "value": obj.value},
        type: 'post',
        success: function(res){
            if(res.code == 0){
                layer.msg(res.msg,{icon:1,time:1000},function(){
                    //layer.close(parent.layer.getFrameIndex(window.name));//关闭当前页
                    layui.table.reload(defaultOption.tableId);
                });
            }else{
                layer.msg(res.msg,{icon:2,time:1000});
            }
        }
    })
}

window.remove = function(obj, option){
    layer.confirm('确定要删除', {icon: 3, title:'提示'}, function(index){
        layer.close(index);
        let loading = layer.load();
        $.ajax({
            url: option.submitUrl,
            data: {"_id": option._id},
            type: 'post',
            success:function(res){
                layer.close(loading);
                if(res.code == 0){
                    layer.msg(res.msg,{icon:1,time:1000},function(){
                        obj.del();
                        table.reload(option.tableId);
                    });
                }else{
                    layer.msg(res.msg,{icon:2,time:1000});
                }
            }
        })
    });
}

window.batchRemove = function(obj, option){
    let data = table.checkStatus(obj.config.id).data;
    if(data.length === 0){
        layer.msg("未选中数据",{icon:3,time:1000});
        return false;
    }
    console.log(data);
    let ids = "";
    for(let i = 0;i<data.length;i++){
        ids += data[i]._id+",";
    }
    ids = ids.substr(0,ids.length-1);
    layer.confirm('确定要删除这些数据', {icon: 3, title:'提示'}, function(index){
        layer.close(index);
        let loading = layer.load();
        $.ajax({
            url: option.submitUrl,
            data: {"_ids": ids},
            type: 'post',
            success:function(res){
                layer.close(loading);
                if(res.code == 0){
                    layer.msg(res.msg,{icon:1,time:1000},function(){
                        table.reload(option.tableId);
                    });
                }else{
                    layer.msg(res.msg,{icon:2,time:1000});
                }
            }
        })
    });
}

window.batchReopenAccountFrozenAccount = function(obj, option){
    let data = table.checkStatus(obj.config.id).data;
    if(data.length === 0){
        layer.msg("未选中数据",{icon:3,time:1000});
        return false;
    }
    //console.log(data);
    let ids = "";
    for(let i = 0;i<data.length;i++){
        ids += data[i].userId+",";
    }
    ids = ids.substr(0,ids.length-1);
    layer.confirm('确定要操作吗', {icon: 3, title:'提示'}, function(index){
        layer.close(index);
        let loading = layer.load();
        $.ajax({
            url: option.submitUrl,
            data: {"_ids": ids, "value": obj.value},
            type: 'post',
            success:function(res){
                layer.close(loading);
                if(res.code == 0){
                    layer.msg(res.msg,{icon:1,time:1000},function(){
                        table.reload(option.tableId);
                    });
                }else{
                    layer.msg(res.msg,{icon:2,time:1000});
                }
            }
        })
    });
}

window.frozenAccountReason = function(obj, option){
    let defaultOption = {"width": '400px', "height": '300px', "title": '冻结账号'};
    $.extend(defaultOption, option);
    let data = table.checkStatus(obj.config.id).data;
    if(data.length === 0){
        layer.msg("未选中数据",{icon:3,time:1000});
        return false;
    }
    //console.log(data);
    let ids = "";
    for(let i = 0;i<data.length;i++){
        ids += data[i].userId+",";
    }
    ids = ids.substr(0,ids.length-1);
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
            let info = body.find('#frozenReason').val();
            $.ajax({
                url: defaultOption.submitUrl,
                data: {"_ids": ids, "value": obj.value, "reason": info},
                type: 'post',
                success:function(res){
                    layer.close(loading);
                    if(res.code == 0){
                        layer.msg(res.msg,{icon:1,time:1000},function(){
                            table.reload(option.tableId);
                        });
                    }else{
                        layer.msg(res.msg,{icon:2,time:1000});
                    }
                }
            });
            layer.close(index);
            table.reload(option.tableId);
        }
    });
}

window.batchReopenWithdrawFrozenWithdraw = function(obj, option){
    let data = table.checkStatus(obj.config.id).data;
    if(data.length === 0){
        layer.msg("未选中数据",{icon:3,time:1000});
        return false;
    }
    //console.log(data);
    let ids = "";
    for(let i = 0;i<data.length;i++){
        ids += data[i].promoterId+",";
    }
    ids = ids.substr(0,ids.length-1);
    layer.confirm('确定要操作吗', {icon: 3, title:'提示'}, function(index){
        layer.close(index);
        let loading = layer.load();
        $.ajax({
            url: option.submitUrl,
            data: {"_ids": ids, "value": obj.value},
            type: 'post',
            success:function(res){
                layer.close(loading);
                if(res.code == 0){
                    layer.msg(res.msg,{icon:1,time:1000},function(){
                        table.reload(option.tableId);
                    });
                }else{
                    layer.msg(res.msg,{icon:2,time:1000});
                }
            }
        })
    });
}

window.save = function(option) {
    let defaultOption = {"tableId": 'table1'};
    $.extend(defaultOption, option);
    $.ajax({
        url: defaultOption.submitUrl,
        data: defaultOption.data,
        type: 'post',
        success: function(res){
            if(res.code == 0){
                $('#subColour').addClass("layui-btn-disabled");
                $('#subColour').attr('disabled',true);
                layer.msg(res.msg,{icon:1,time:2000},function(){
                    parent.layer.close(parent.layer.getFrameIndex(window.name));//关闭当前页
                    parent.layui.table.reload(defaultOption.tableId);
                });
            }else{
                layer.msg(res.msg,{icon:2,time:3000});
            }
            if(defaultOption.btn != undefined) {
                $('#'+defaultOption.btn).removeClass("layui-btn-disabled");
                $('#'+defaultOption.btn).removeAttr('disabled');
            }
            if(defaultOption.callback != undefined) {
                defaultOption.callback();
            }
        }
    })
}

window.promotionSave = function(option) {
    let defaultOption = {"tableId": 'table1'};
    $.extend(defaultOption, option);
    $.ajax({
        url: defaultOption.submitUrl,
        data: defaultOption.data,
        type: 'post',
        success: function(res){
            if(res.code == 0){
                $('#subColour').addClass("layui-btn-disabled");
                $('#subColour').attr('disabled',true);
                layer.msg(res.msg,{icon:1,time:2000},function(){
                    parent.layer.close(parent.layer.getFrameIndex(window.name));//关闭当前页
                    parent.layui.table.reload(defaultOption.tableId);
                });
                setTimeout(function(){
                    window.location.reload();
                },2000)
            }else if(res.code == -2){
                $('#subColour').addClass("layui-btn-disabled");
                $('#subColour').attr('disabled',true);
                layer.msg(res.msg,{icon:1,time:4000},function(){
                    parent.layer.close(parent.layer.getFrameIndex(window.name));//关闭当前页
                    parent.layui.table.reload(defaultOption.tableId);
                });
                setTimeout(function(){
                    window.location.reload();
                },4000)
            }else{
                layer.msg(res.msg,{icon:2,time:3000});
                setTimeout(function(){
                    window.location.reload();
                },2000)
            }
            if(defaultOption.btn != undefined) {
                $('#'+defaultOption.btn).removeClass("layui-btn-disabled");
                $('#'+defaultOption.btn).removeAttr('disabled');
            }
            if(defaultOption.callback != undefined) {
                defaultOption.callback();
            }
        }
    })
}

window.sendNotice = function(obj, option){
    layer.confirm('是否发送强制弹窗？', {icon: 3, title:'提示'}, function(index){
        layer.close(index);
        let loading = layer.load();
        $.ajax({
            url: option.submitUrl,
            data: {"_id": option._id},
            type: 'post',
            success:function(res){
                layer.close(loading);
                if(res.code == 0){
                    layer.msg(res.msg,{icon:1,time:1000},function(){
                        //obj.del();
                    });
                }else{
                    layer.msg(res.msg,{icon:2,time:1000});
                }
            }
        })
    });
}

window.saveEmail = function(option) {
    let defaultOption = {"tableId": 'table1'};
    $.extend(defaultOption, option);
    if (verify(defaultOption)){
        $("#emailtijiao").attr("disabled",option.disabled)
        $.ajax({
            url: defaultOption.submitUrl,
            data: defaultOption.data,
            type: 'post',
            success: function(res){
                if(res.code == 0){
                    layer.msg(res.msg,{icon:1,time:1000},function(){
                        parent.layer.close(parent.layer.getFrameIndex(window.name));//关闭当前页
                        parent.layui.table.reload(defaultOption.tableId);
                    });
                }else{
                    $("#emailtijiao").removeAttr("disabled")
                    layer.msg(res.msg,{icon:2,time:1000});

                }
                if(defaultOption.btn != undefined) {
                    $('#'+defaultOption.btn).removeClass("layui-btn-disabled");
                    $('#'+defaultOption.btn).removeAttr('disabled');
                }
            }
        })
    }
}

window.downFile = function(newFile) {
    window.open('http://47.243.92.221:8090/'+newFile);
}

function verify(defaultOption,str) {
    var rewardScore = defaultOption.data.rewardScore;
    var userId = defaultOption.data.userId;
    var expireTime = defaultOption.data.expireTime;
    var sendTime = defaultOption.data.sendTime;
    var title = defaultOption.data.title;
    var content = defaultOption.data.content;
    var strLenTit = getLength(title);
    var strLenCon = getLength(content);
    var uidVerify = /^\d+$/;
    var rewardScoreVerify = /^\d+(\.\d+)?$/

    if(rewardScoreVerify.test(rewardScore) == false){
        layerMsg('赠送货币:只能输入正整数或者正小数');
        return false;
    }
    if(rewardScore != 0){
        var userId = defaultOption.data.userId;
        if( rewardScore < 0.01 || rewardScore > 10){
            layerMsg('赠送货币:最小为0.01,最多为10个!');
            return false;
        }
        if(userId == 0){
            layerMsg('赠送货币:当目标用户为0时,赠送货币只能为0!');
            return false;
        }
    }
    if(uidVerify.test(userId) == false){
        layerMsg('目标用户:只能输入正整数或者0');
        return false;
    }
    if( expireTime < sendTime){
        layerMsg('过期时间:过期时间必须大于发布时间');
        return false;
    }
    if(strLenTit > 16){
        layerMsg('标题:标题字符最多为16,中文占2个字符');
        return false;
    }
    if(strLenCon > 1024){
        layerMsg('内容:内容字符最多为1k,中文占2个字符');
        return false;
    }
    return true;
}
function layerMsg (msg){
    layer.msg(msg,{icon:2,time:2000});
}
function getLength (str) {
    //获得字符串实际长度，中文2，英文1
    //要获得长度的字符串
    var realLength = 0, len = str.length, charCode = -1;
    for (var i = 0; i < len; i++) {
        charCode = str.charCodeAt(i);
        if (charCode >= 0 && charCode <= 128)
            realLength += 1;
        else
            realLength += 2;
    }
    return realLength;
};