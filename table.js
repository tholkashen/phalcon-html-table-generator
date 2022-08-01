const tableManager = {
    tables: {},
    init: function (name, url, container) {
        this.tables[name] = new TableInstance(name, url, container);
        return this.tables[name];
    },
    get: function (name) {
        if (this.tables[name] !== undefined) {
            return this.tables[name];
        }
    }
}
class TableInstance {
    constructor(name, url, container, options = {}) {
        this.name = name;
        this.url = url;
        this.options = options;
        this.selected = null;
        if (typeof container == "object") {
            this.container = container;
        } else {
            this.container = $(container);
        }
        this.staticFilters = {};
        this.requests = {
            name: this.name,
            filters: {},
            custom_filters: {},
            mode: "table",
            limit: 50,
            page: 1,
            extra: {},
            sort: {}
        };
        this.last = {
            filter: null
        },
        this.templates = {
            filter: `<div class="input-group input-group-sm mr-1 ml-1" style="flex-wrap:nowrap">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-primary">{{filterName}}</span>
                        </div>
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white">{{filterValue}}</span>
                        </div>
                        <div class="input-group-append">
                            <button class="btn btn-sm btn-default {{filterCloseClass}}" data-filter-item="{{filterID}}" data-filter-action="remove">
                                <span class="fa fa-trash"></span>
                            </button>
                        </div>
                    </div>`,
            custom_filter: `<div class="input-group input-group-sm mr-1 ml-1" style="flex-wrap:nowrap">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-primary">{{filterName}}</span>
                                </div>
                                <div class="input-group-append">
                                    <button class="btn btn-sm btn-default custom-filter" data-filter-item="{{filterID}}" data-filter-action="remove">
                                        <span class="fa fa-trash"></span>
                                    </button>
                                </div>
                            </div>`
        }
    }
    ready(callback) {
        var t = this;
        t.container.on('table_ready', function (e, name, response) {
            callback(t.container, name, response);
        });
    }
    setParam(name, value) {
        this.requests.extra[name] = value;
    }
    setFilters(data) {
        Object.entries(data).forEach(el => {
            if (el[0] == "mode" || el[0] == "page" || el[0] == "limit" || el[0] == "_url") {
                return;
            }
            this.requests.filters[el[0]] = el[1];
        });
    }
    setLimit(limit) {
        var t = this;
        this.requests.limit = limit;
        url.setParam("limit", this.requests.limit);
        t.container.trigger('table_param_changed', [this.name, "limit"]);
    }
    setPage(page) {
        var t = this;
        this.requests.page = page;
        t.container.trigger('table_param_changed', [this.name, "page"]);
    }
    async getRawData() {
        var t = this;
        t.container.find('.table-spinner').removeClass('d-none');
        console.log(t.requests);
        $.get(this.url, this.requests, function (response) {
            console.log(response);
            t.container.trigger('table_fetched', [t.name, response]);
        });
    }
    _renderPagination(data) {
        var t = this;
        if (typeof data != "undefined") {
            var html = "";
            for (let i = 1; i <= data.last_page; i++) {
                let attr = "";
                if (data.current_page == i) {
                    attr += "selected";
                }
                html += "<option " + attr + " value='" + i + "'>" + i + "</option>";
            }
            if (t.container.find('.pagination-selector').length > 0) {
                let selector = t.container.find('.pagination-selector');
                selector.html(html);
                selector.off('change').on('change', function () {
                    t.setPage($(this).val());
                });
            }
        }
    }
    _renderCustomFilterList(data) {
        var t = this;
        if (typeof data['custom_filters'] != "undefined") {
            let items = "";
            let item_template = `<a class="dropdown-item table-custom-filter {{class}}" data-table-custom-filter="{{filterID}}" data-table="{{tableID}}" href="javascript:void(0)">{{filterName}}</a>`;
            Object.entries(data.custom_filters).forEach((val) => {
                let _buffer = item_template;
                _buffer = _buffer.replace("{{filterID}}", val[0]);
                _buffer = _buffer.replace("{{tableID}}", t.name);
                _buffer = _buffer.replace("{{filterName}}", val[1].title);
                if (val[1].status == true) {
                    _buffer = _buffer.replace("{{class}}", "active");
                } else {
                    _buffer = _buffer.replace("{{class}}", "");
                } items += _buffer;
            });
            let customFilterPanel = t.container.find('.custom-filters');

            customFilterPanel.html(items);
            customFilterPanel.find('.table-custom-filter').off('click').on('click', function (e) {
                let filterID = $(this).attr('data-table-custom-filter');
                if ($(this).hasClass('active')) {
                    delete t.requests.custom_filters[filterID];
                } else {
                    t.requests.custom_filters[filterID] = true;
                } t.container.trigger('table_param_changed', [t.name, "custom_filter"]);
            });

        }
    }
    _renderScripts(data) {
        var t = this;
        if (data.scripts !== undefined) {
            Object.entries(data.scripts).forEach((val) => {
                t.container.find('.data-container').append(val[1]);

            });
        }
    }
    _renderStyles(data) {
        var t = this;
        if (data.styles !== undefined) {
            Object.entries(data.styles).forEach((val) => {
                $('head').append(val[1]);
            });
        }
    }
    _renderFilterIndicator(data) {
        var t = this;
        let indicators = [];
        Object.entries(t.requests.filters).forEach((val) => {
            let template = t.templates.filter;
            let filterName = data.filterable_columns[val[0]];
            if (val[0] == "__") {
                filterName = "Tümü";
            }
            template = template.replace("{{filterName}}", filterName);
            template = template.replace("{{filterID}}", val[0]);
            if(typeof t.staticFilters[val[0]] != "undefined"){
                template = template.replace("{{filterCloseClass}}", "disabled");
            }else{
                template = template.replace("{{filterCloseClass}}", "");
            }
            if(Array.isArray(val[1])){
                template = template.replace("{{filterValue}}", val[1].join(" || "));
            }else{
                template = template.replace("{{filterValue}}", val[1]);
            }
            
            indicators.push(template);
        });
        Object.entries(data.custom_filters).forEach((val) => {
            if (val[1].status == true) {
                let template = t.templates.custom_filter;
                let filterName = val[1].title;
                template = template.replace("{{filterName}}", filterName);
                template = template.replace("{{filterID}}", val[0]);
                indicators.push(template);
            }

        });
        let filterIndicatorContainer = t.container.find('.filter-panel');
        filterIndicatorContainer.html(indicators.join(" "));
        filterIndicatorContainer.find('button[data-filter-action="remove"]').off('click').on('click', function () {
            let id = $(this).attr('data-filter-item');
            if ($(this).hasClass('custom-filter')) {
                delete t.requests.custom_filters[id];
                t.container.trigger('table_param_changed', [t.name, "custom_filter"]);
            } else {
                if(typeof t.staticFilters[id] == "undefined"){
                    delete t.requests.filters[id];
                    t.container.trigger('table_param_changed', [t.name, "filter"]);
                }
                
            }
        });
    }
    _renderFilterList(data) {
        var t = this;
        if (typeof data != "undefined") {
            var html = "<option value='__'>Tümü</option>";
            Object.entries(data.filterable_columns).forEach((val) => {
                let attr = "";
                if (t.last.filter == val[0]) {
                    attr = "selected";
                }
                html += "<option value='" + val[0] + "' " + attr + ">" + val[1] + "</option>";
            });
            let searchPanel = t.container.find('.search-column-selector');
            let searchValue = t.container.find('.search-value-input');
            searchPanel.html(html);
            searchValue.off('keyup').on('keyup', function (e) {
                if (e.key == "Enter") {
                    let filterColumn = searchPanel.val();
                    if (t.requests.filters[filterColumn] !== undefined) {
                        t.requests.filters[filterColumn].push($(this).val().trim());
                    } else {
                        t.requests.filters[filterColumn] = [$(this).val().trim()];
                    } t.last.filter = filterColumn;
                    searchValue.val(null);
                    t.requests.page = 1;
                    t.container.trigger('table_param_changed', [t.name, "filter"]);
                }
            });
        }
    }
    _sortingInit() {
        var t = this;
        if (t.requests.sort.col !== undefined) {
            t.container.find('table thead th.sortable[data-column="' + t.requests.sort.col + '"]').addClass('table-sort-active');
        }
        t.container.find('table thead th.sortable').off('click').on('click', function () {
            let dir = "asc";
            if ($(this).hasClass('sorted-asc')) {
                dir = "desc";
            }
            t.requests.sort = {
                col: $(this).attr('data-column'),
                dir: dir
            };
            t.container.trigger('table_param_changed', [t.name, "sort"]);
        });
    }
    _modeSelectorInit() {
        var t = this;
        if (url.hasParam('mode')) {
            t.requests.mode = url.getParam('mode');
        }
        $('.table-view-selector').off('click').on('click', function () {
            if ($(this).attr('data-table') == t.name) {
                console.log($(this).attr('data-table-view-select'));
                url.setParam('mode', $(this).attr('data-table-view-select'));
            }

        });
        $(window).on('paramschanged', function (e, param, value) {
            if (param == "mode") {
                t.requests.mode = value;
                t.container.trigger('table_param_changed', [t.name, "mode"]);
            }
        });
    }

    _getParametersInit() {
        var t = this;
        if (url.hasParam('limit')) {
            t.setLimit(url.getParam('limit'));
        }
        if (url.hasParam('page')) {
            t.getPage(url.getParam('page'));
        }
        Object.entries(url.getParams()).forEach((val) => {
            let firstLetter = val[0][0];
            if (val[0] == "mode" || val[0] == "page" || val[0] == "limit" || firstLetter == "_") {
                return;
            }
            if (t.requests.filters[val[0]] === undefined) {
                t.requests.filters[val[0]] = [];
                t.staticFilters[val[0]] = true;
            }
            t.requests.filters[val[0]].push(val[1]);
        });
    }
    getTable() {
        var t = this;
        t._modeSelectorInit();
        t._getParametersInit();
        this.getRawData();
        t.container.on('table_fetched', function (e, name, resp) {
            t._renderPagination(resp);
            t._renderFilterList(resp);
            t._renderCustomFilterList(resp);
            t._renderFilterIndicator(resp);
            t.container.find('.data-container').html(resp.html);
            t._sortingInit();
            t._renderStyles(resp);
            t._renderScripts(resp);


            t.container.trigger('table_ready', [t.name, resp]);
        });
        t.container.on('table_param_changed', function (e, name) {
            t.getRawData();
        });
        t.container.on('table_ready', function () {
            t.container.find('nav ul li .table-spinner').addClass('d-none');
            if (t.selected != null) {
                t.container.find('table tbody tr[data-id="' + t.selected + '"]').addClass('row-selected');
            }
            t.container.find('table tbody tr:not(.unselectable)').off('click').on('click', function () {
                t.container.find('table tbody tr:not(.unselectable)').each(function () {
                    $(this).removeClass('row-selected');
                });
                t.selected = $(this).attr('data-id');
                $(this).addClass('row-selected');
                t.container.trigger('table_row_selected', [t.name, t.selected]);
            });
        });
    }

}
