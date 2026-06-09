!function () {
    let table, _createForms = [];

    const statusText = status => {
        const map = {
            0: format.badge("未领取", "a-badge-primary"),
            1: format.badge("已领取完", "a-badge-success"),
            2: format.badge("已锁定", "a-badge-dark")
        };
        return map[status] ?? "-";
    };

    const cardStatusText = status => {
        const map = {
            0: format.badge("未售", "a-badge-primary"),
            1: format.badge("已售", "a-badge-success"),
            2: format.badge("锁定", "a-badge-dark")
        };
        return map[status] ?? "-";
    };

    const resetSkuForms = form => {
        form.setRadio("race_get_mode", 0, true);
        form.setInput("race_input", "");
        form.hide("race");
        form.hide("race_input");
        form.clearComponent("race");
        form.hide("race_get_mode");
        _createForms.forEach(k => form.removeForm(k));
        _createForms = [];
    };

    const loadSkuForms = (form, commodityId) => {
        resetSkuForms(form);
        if (commodityId <= 0) {
            return;
        }
        util.get(`/admin/api/card/sku?commodityId=${commodityId}`, data => {
            if (!util.isEmptyOrNotJson(data?.category)) {
                let i = 0;
                for (const cKey in data.category) {
                    form.addRadio("race", cKey, cKey, i === 0);
                    i++;
                }
                form.show("race");
                form.show("race_get_mode");
            }
            if (!util.isEmptyOrNotJson(data?.sku)) {
                for (const sKey in data.sku) {
                    let dict = [];
                    for (const sk in data.sku[sKey]) {
                        dict.push({id: sk, name: sk});
                    }
                    form.createForm({
                        title: sKey,
                        name: `sku.${sKey}`,
                        type: "radio",
                        dict: dict
                    }, "race", "after");
                    _createForms.push(`sku-${sKey}`);
                }
            }
        });
    };

    const importJson = () => {
        component.popup({
            submit: '/admin/api/jsonPickup/import',
            tab: [
                {
                    name: util.icon("fa-duotone fa-regular fa-file-code") + " 导入 JSON 提卡",
                    form: [
                        {
                            title: "选择商品",
                            name: "commodity_id",
                            type: "select",
                            dict: "commodity->owner=0 and delivery_way=0 and (shared_id is null or shared_id=0),id,name",
                            placeholder: "请选择自动发货商品",
                            search: true,
                            change: loadSkuForms
                        },
                        {
                            title: "种类获取方法",
                            name: "race_get_mode",
                            type: "radio",
                            dict: [{id: 0, name: "自动获取"}, {id: 1, name: "手动填写"}],
                            hide: true,
                            change: (form, val) => {
                                if (val == 1) {
                                    form.hide("race");
                                    form.show("race_input");
                                } else {
                                    form.show("race");
                                    form.hide("race_input");
                                }
                            }
                        },
                        {
                            title: "商品种类",
                            name: "race_input",
                            type: "input",
                            placeholder: "填写商品种类",
                            hide: true
                        },
                        {
                            title: "商品种类",
                            name: "race",
                            type: "radio",
                            hide: true
                        },
                        {
                            title: "导入模式",
                            name: "mode",
                            type: "radio",
                            dict: [
                                {id: "whole", name: "整个 JSON 作为一张卡"},
                                {id: "array_items", name: "数组/accounts 每项一张卡"},
                                {id: "jsonl", name: "JSONL 每行一张卡"}
                            ],
                            default: "whole"
                        },
                        {
                            title: "上传文件",
                            name: "source_file",
                            type: "file",
                            uploadUrl: "/admin/api/jsonPickup/upload",
                            placeholder: "上传 .json 或 .jsonl 文件"
                        },
                        {
                            title: "JSON 内容",
                            name: "payload",
                            type: "textarea",
                            placeholder: "也可以不上传文件，直接粘贴 JSON 或 JSONL 内容",
                            height: 220
                        },
                        {
                            title: "提卡码前缀",
                            name: "prefix",
                            type: "input",
                            placeholder: "例如 JP",
                            default: "JP"
                        },
                        {
                            title: "可下载次数",
                            name: "max_downloads",
                            type: "number",
                            placeholder: "默认 1 次",
                            default: 1
                        },
                        {
                            title: "过期时间",
                            name: "expire_time",
                            type: "date",
                            placeholder: "不填表示不过期"
                        },
                        {
                            title: "批次备注",
                            name: "batch_no",
                            type: "input",
                            placeholder: "可空，便于后续筛选"
                        }
                    ]
                }
            ],
            autoPosition: true,
            height: "auto",
            width: "760px",
            done: res => {
                table.refresh();
                layer.open({
                    type: 1,
                    title: "JSON 提卡码 [成功:" + res.data.success + "]",
                    area: util.isPc() ? ['460px', '660px'] : ["100%", "100%"],
                    content: '<textarea class="layui-input" style="padding: 15px;height: 100%;line-height:18px;">' + res.data.codes + '</textarea>'
                });
            }
        });
    };

    const modal = (title, assign = {}) => {
        component.popup({
            submit: '/admin/api/jsonPickup/edit',
            tab: [
                {
                    name: title,
                    form: [
                        {title: "文件名", name: "filename", type: "input", required: true},
                        {title: "可下载次数", name: "max_downloads", type: "number", required: true},
                        {title: "过期时间", name: "expire_time", type: "date", placeholder: "不填表示不过期"},
                        {
                            title: "状态",
                            name: "status",
                            type: "radio",
                            dict: [
                                {id: 0, name: "未领取"},
                                {id: 1, name: "已领取完"},
                                {id: 2, name: "锁定"}
                            ]
                        }
                    ]
                }
            ],
            assign: assign,
            autoPosition: true,
            maxmin: false,
            height: "auto",
            width: "560px",
            done: () => table.refresh()
        });
    };

    table = new Table("/admin/api/jsonPickup/data", "#json-pickup-table");
    table.setUpdate("/admin/api/jsonPickup/edit");
    table.setColumns([
        {checkbox: true},
        {field: 'code', title: '提卡码'},
        {field: 'commodity', title: '商品', formatter: format.item},
        {field: 'filename', title: '文件名'},
        {field: 'batch_no', title: '批次'},
        {field: 'size', title: '大小', formatter: _ => format.size ? format.size(_) : (_ + " B")},
        {field: 'download_count', title: '下载次数', formatter: (_, row) => `${row.download_count}/${row.max_downloads}`},
        {field: 'status', title: '提卡状态', formatter: statusText},
        {field: 'card.status', title: '售卖状态', formatter: (_, row) => row.card ? cardStatusText(row.card.status) : "-"},
        {field: 'expire_time', title: '过期时间', formatter: _ => _ || "永久"},
        {field: 'last_download_time', title: '最后下载'},
        {field: 'create_time', title: '创建时间'},
        {
            field: 'operation', title: '操作', type: 'button', buttons: [
                {
                    icon: 'fa-duotone fa-regular fa-pen-to-square',
                    class: "text-success",
                    click: (event, value, row) => modal(util.icon("fa-duotone fa-regular fa-pen-to-square me-1") + "编辑 JSON 提卡", row)
                },
                {
                    icon: 'fa-duotone fa-regular fa-lock-keyhole',
                    class: "text-primary",
                    show: _ => _.status == 0,
                    click: (event, value, row) => {
                        util.post('/admin/api/jsonPickup/edit', {id: row.id, status: 2}, () => {
                            message.success(`【${row.code}】已锁定`);
                            table.refresh();
                        });
                    }
                },
                {
                    icon: 'fa-duotone fa-regular fa-lock-keyhole-open',
                    class: "text-success",
                    show: _ => _.status == 2,
                    click: (event, value, row) => {
                        util.post('/admin/api/jsonPickup/edit', {id: row.id, status: 0}, () => {
                            message.success(`【${row.code}】已解锁`);
                            table.refresh();
                        });
                    }
                },
                {
                    icon: 'fa-duotone fa-regular fa-trash-can',
                    class: "text-danger",
                    click: (event, value, row) => {
                        message.ask("删除后买家将无法再通过该提卡码下载 JSON，确定继续？", () => {
                            util.post('/admin/api/jsonPickup/del', {list: [row.id]}, () => {
                                message.success("删除成功");
                                table.refresh();
                            });
                        });
                    }
                }
            ]
        }
    ]);
    table.setPagination(15, [15, 30, 50, 100]);
    table.setSearch([
        {title: "提卡码", name: "equal-code", type: "input"},
        {title: "批次", name: "equal-batch_no", type: "input"},
        {title: "文件名", name: "search-filename", type: "input"},
        {
            title: "商品",
            name: "equal-commodity_id",
            type: "select",
            dict: "commodity->owner=0 and delivery_way=0 and (shared_id is null or shared_id=0),id,name",
            search: true
        },
        {
            title: "提卡状态",
            name: "equal-status",
            type: "select",
            dict: [{id: 0, name: "未领取"}, {id: 1, name: "已领取完"}, {id: 2, name: "锁定"}]
        },
        {title: "创建时间", name: "between-create_time", type: "date"}
    ]);
    table.render();

    $('.btn-app-create').click(importJson);
    $('.btn-app-del').click(() => {
        let data = table.getSelectionIds();
        if (data.length === 0) {
            layer.msg("请至少勾选一条记录");
            return;
        }
        message.ask("删除后买家将无法再通过这些提卡码下载 JSON，确定继续？", () => {
            util.post("/admin/api/jsonPickup/del", {list: data}, () => {
                message.success("删除成功");
                table.refresh();
            });
        });
    });
    $('.btn-app-lock').click(() => {
        let data = table.getSelectionIds();
        if (data.length === 0) {
            layer.msg("请至少勾选一条记录");
            return;
        }
        util.post("/admin/api/jsonPickup/lock", {list: data}, () => {
            message.success("锁定成功");
            table.refresh();
        });
    });
    $('.btn-app-unlock').click(() => {
        let data = table.getSelectionIds();
        if (data.length === 0) {
            layer.msg("请至少勾选一条记录");
            return;
        }
        util.post("/admin/api/jsonPickup/unlock", {list: data}, () => {
            message.success("解锁成功");
            table.refresh();
        });
    });
}();
