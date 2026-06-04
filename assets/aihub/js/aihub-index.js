!function () {
    const $SwitchCategory = $(`.switch-category`), $ItemList = $(`.item-list`), categoryId = getVar("CAT_ID"),
          defaultCover = '/assets/aihub/images/default-cover.svg';

    function _PushCommodityList(data) {
        $ItemList.html("");

        if (data.length == 0) {
            layer.msg("暂无可用模型");
            $ItemList.html(`<div class="col-span-full text-center text-slate-400 py-12"><i class="fas fa-cube text-3xl mb-3 block opacity-50"></i>暂无可用模型/套餐</div>`);
            return;
        }

        data.forEach(item => {
            const isSoldOut = item.stock == 0;
            const coverUrl = item.cover || defaultCover;

            $ItemList.append(`<a href="${!isSoldOut ? `/item/${item.id}` : `javascript:void(0);`}" class="block" data-id="${item.id}" style="text-decoration:none;">
          <div class="model-card ${isSoldOut ? `soldout` : ``} h-full flex flex-col">
            <div class="model-cover w-full rounded-lg mb-3 overflow-hidden bg-slate-100 flex items-center justify-center" style="height:140px;background-image:url('${coverUrl}');background-size:cover;background-position:center;">
            </div>
            <div class="flex items-center gap-2 mb-2">
              <span class="ai-badge ai-badge-green text-xs">${item.delivery_way === 0 ? '自动发货' : '在线发货'}</span>
              ${item.recommend == 1 ? `<span class="ai-badge ai-badge-purple text-xs">推荐</span>` : ``}
            </div>
            <h3 class="text-slate-800 font-semibold text-base mb-2 truncate">${item.name}</h3>
            <div class="mt-auto">
              <div class="flex items-baseline gap-1 mb-3">
                <span class="text-xs text-indigo-500">¥</span>
                <span class="text-xl font-bold text-indigo-600">${item.price}</span>
                <span class="text-xs text-slate-400 ml-1">起</span>
              </div>
              <div class="flex items-center justify-between text-xs text-slate-400">
                <span>库存: ${item.stock}</span>
                <span>已售: ${(item.purchase_count || 0) + item.order_sold}</span>
              </div>
            </div>
            ${isSoldOut ? `<div class="soldout-ribbon">售罄</div>` : ``}
          </div>
        </a>`);
        });

        // Validate cover images: if background-image fails, show fallback icon
        $ItemList.find('.model-cover').each(function() {
            const $el = $(this);
            const bgUrl = $el.css('background-image');
            if (bgUrl && bgUrl !== 'none') {
                const img = new Image();
                const url = bgUrl.replace(/url\(["']?/, '').replace(/["']?\)/, '');
                img.onload = function() {
                    // Check if the response is actually an image (not HTML masquerading as one)
                    if (this.naturalWidth === 0) {
                        $el.css('background-image', 'none');
                        $el.css('background-image', `url('${defaultCover}')`);
                    }
                };
                img.onerror = function() {
                    $el.css('background-image', 'none');
                    $el.css('background-image', `url('${defaultCover}')`);
                };
                img.src = url;
            }
        });
    }

    function _SwitchCategory(id, link = false) {
        $SwitchCategory.removeClass("is-primary");
        $(`a[data-id=${id}]`).addClass("is-primary");
        if (link) {
            history.pushState(null, '', `/cat/${id}`);
        }
        trade.getCommodityList({
            categoryId: id,
            done: data => {
                _PushCommodityList(data);
            }
        });
    }

    function _Search(keywords) {
        if (keywords == '') {
            layer.msg("请输入要搜索的关键词");
            return;
        }
        $SwitchCategory.removeClass("is-primary");
        trade.getCommodityList({
            keywords: keywords,
            done: data => {
                _PushCommodityList(data);
            }
        });
    }

    _SwitchCategory(categoryId > 0 ? categoryId : $SwitchCategory.first().data("id"));

    $SwitchCategory.click(function () {
        if ($(this).hasClass("is-primary")) return;
        _SwitchCategory($(this).data("id"), true);
    });

    $('.item-search-input').on('keypress', function (e) {
        if (e.which === 13) {
            _Search($(this).val());
        }
    });
}();
