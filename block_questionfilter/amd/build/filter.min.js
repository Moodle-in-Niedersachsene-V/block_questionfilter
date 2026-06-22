// block_questionfilter/amd/src/filter.js
define(['core/ajax'], function(Ajax) {
    'use strict';

    var QTYPES = {
        multichoice:'Multiple Choice', truefalse:'Wahr/Falsch',
        shortanswer:'Kurzantwort',     numerical:'Numerisch',
        essay:'Freitext',              match:'Zuordnung',
        ddwtos:'Drag & Drop',          gapselect:'Lückentext',
        calculated:'Berechnet',        description:'Beschreibung',
    };
    var TYPE_COLOR = {
        multichoice:'bg-success bg-opacity-10 text-success',
        truefalse:  'bg-info bg-opacity-10 text-info',
        shortanswer:'bg-warning bg-opacity-10 text-warning',
        numerical:  'bg-primary bg-opacity-10 text-primary',
        essay:      'bg-secondary bg-opacity-10 text-secondary',
        match:      'bg-info bg-opacity-10 text-info',
    };

    // ---------------------------------------------------------------
    function BlockState(blockid, config) {
        this.blockid    = blockid;
        this.config     = config;
        this.search     = '';
        this.cats       = {};      // {catid: true}
        this.types      = {};
        this.diffs      = {};
        this.tags       = [];
        this.selected   = {};
        this.results    = [];
        this.allCats    = [];
        this.panelOpen  = false;
        this.panelQuery = '';
        this.debounce   = null;
    }

    BlockState.prototype.el = function(id) {
        return document.getElementById('qf-' + id + '-' + this.blockid);
    };
    BlockState.prototype.block = function() {
        return document.getElementById('qf-block-' + this.blockid);
    };

    // ---------------------------------------------------------------
    // Init
    // ---------------------------------------------------------------
    BlockState.prototype.init = function() {
        var self = this;

        self.renderTypeChips();

        // Diff-Chips
        self.block().querySelectorAll('.qf-chip-diff').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var v = this.dataset.value;
                if (self.diffs[v]) { delete self.diffs[v]; this.classList.remove('qf-chip-active'); }
                else               { self.diffs[v] = true;  this.classList.add('qf-chip-active'); }
                self.triggerSearch();
            });
        });

        // Kombiniertes Suchfeld
        var searchEl = self.el('search');
        if (searchEl) {
            searchEl.addEventListener('input', function() {
                self.parseSearchInput(this.value);
            });
            searchEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    var val = this.value.trim();
                    if (val.charAt(0) === '#' && val.length > 1) {
                        self.addTag(val.substring(1));
                        this.value = '';
                        self.search = '';
                        self.triggerSearch();
                    }
                }
            });
        }

        // Dropdown-Toggle
        var toggleBtn = self.el('cat-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                self.togglePanel();
            });
        }

        // Eingabefeld im Dropdown-Header öffnet Panel
        var filterInput = self.el('cat-filter');
        if (filterInput) {
            filterInput.addEventListener('click', function(e) {
                e.stopPropagation();
                if (!self.panelOpen) self.openPanel();
            });
        }

        // Suchfeld im Panel
        var panelSearch = self.el('cat-search');
        if (panelSearch) {
            panelSearch.addEventListener('input', function() {
                self.panelQuery = this.value.toLowerCase();
                self.renderPanelList();
            });
            panelSearch.addEventListener('click', function(e) { e.stopPropagation(); });
        }

        // Alle / Keine
        var wrap = self.el('catdrop-wrap');
        if (wrap) {
            var selAll = wrap.querySelector('.qf-cat-sel-all');
            var selNone = wrap.querySelector('.qf-cat-sel-none');
            if (selAll) selAll.addEventListener('click', function(e) {
                e.stopPropagation();
                self.filteredCats().forEach(function(c) { self.cats[c.id] = true; });
                self.renderPanelList();
                self.updateCatInput();
                self.triggerSearch();
            });
            if (selNone) selNone.addEventListener('click', function(e) {
                e.stopPropagation();
                self.filteredCats().forEach(function(c) { delete self.cats[c.id]; });
                self.renderPanelList();
                self.updateCatInput();
                self.triggerSearch();
            });
        }

        // X-Button: Auswahl aufheben
        var clearBtn = self.el('cat-clear');
        if (clearBtn) {
            clearBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                self.cats = {};
                self.updateCatInput();
                self.renderPanelList();
                self.triggerSearch();
            });
        }

        // Klick außerhalb → Panel schließen
        document.addEventListener('click', function(e) {
            if (self.panelOpen) {
                var wrap = self.el('catdrop-wrap');
                if (wrap && !wrap.contains(e.target)) self.closePanel();
            }
        });

        // Alle wählen (Ergebnisse)
        var selAll = self.block().querySelector('.qf-select-all');
        if (selAll) selAll.addEventListener('click', function() { self.toggleSelectAll(); });

        // Export
        self.block().querySelectorAll('.qf-export').forEach(function(btn) {
            btn.addEventListener('click', function() { self.doExport(this.dataset.format); });
        });
        var addQuiz = self.block().querySelector('.qf-add-to-quiz');
        if (addQuiz) addQuiz.addEventListener('click', function() { self.addToQuiz(); });

        self.loadCategories();
    };

    // ---------------------------------------------------------------
    // Dropdown Panel
    // ---------------------------------------------------------------
    BlockState.prototype.openPanel = function() {
        var self  = this;
        var panel = self.el('cat-panel');
        var icon  = self.el('cat-toggle');
        if (!panel) return;
        panel.style.display = 'flex';
        self.panelOpen = true;
        if (icon) icon.innerHTML = '<i class="fa fa-chevron-up" style="font-size:11px"></i>';
        // Suchfeld fokussieren
        var ps = self.el('cat-search');
        if (ps) setTimeout(function() { ps.focus(); }, 50);
    };

    BlockState.prototype.closePanel = function() {
        var self  = this;
        var panel = self.el('cat-panel');
        var icon  = self.el('cat-toggle');
        if (!panel) return;
        panel.style.display = 'none';
        self.panelOpen = false;
        if (icon) icon.innerHTML = '<i class="fa fa-chevron-down" style="font-size:11px"></i>';
    };

    BlockState.prototype.togglePanel = function() {
        if (this.panelOpen) this.closePanel(); else this.openPanel();
    };

    // ---------------------------------------------------------------
    // Kategorien
    // ---------------------------------------------------------------
    BlockState.prototype.loadCategories = function() {
        var self      = this;
        var filterEl  = self.el('cat-filter');
        Ajax.call([{
            methodname: 'block_questionfilter_get_categories',
            args: { scope: self.config.searchscope || 'all', contextid: self.config.contextid || 1 },
            done: function(result) {
                self.allCats = result.categories || [];
                if (filterEl) {
                    filterEl.placeholder = self.allCats.length + ' Sammlungen verfügbar …';
                    filterEl.readOnly = false;
                }
                self.updateCatInput();
                self.renderPanelList();
            },
            fail: function() {
                if (filterEl) filterEl.placeholder = 'Laden fehlgeschlagen';
            },
        }]);
    };

    // Gefilterte Liste (nach panelQuery)
    BlockState.prototype.filteredCats = function() {
        var q = this.panelQuery;
        if (!q) return this.allCats;
        return this.allCats.filter(function(c) {
            return c.name.toLowerCase().indexOf(q) !== -1
                || (c.contextlabel || '').toLowerCase().indexOf(q) !== -1;
        });
    };

    // Zeigt gewählte Sammlungen im Eingabefeld an
    BlockState.prototype.updateCatInput = function() {
        var self     = this;
        var filterEl = self.el('cat-filter');
        var clearBtn = self.el('cat-clear');
        var label    = self.el('cat-label');
        var n        = Object.keys(self.cats).length;
        var total    = self.allCats.length;

        if (filterEl) {
            if (n === 0) {
                filterEl.value = '';
                filterEl.placeholder = total + ' Sammlungen verfügbar …';
            } else if (n === 1) {
                var id  = parseInt(Object.keys(self.cats)[0], 10);
                var cat = self.allCats.filter(function(c) { return c.id === id; })[0];
                filterEl.value = cat ? cat.name : '1 gewählt';
            } else {
                filterEl.value = n + ' Sammlungen gewählt';
            }
        }
        if (clearBtn) clearBtn.style.display = n > 0 ? '' : 'none';
        if (label) {
            label.textContent = 'FRAGESAMMLUNGEN'
                + (n > 0 ? ' (' + n + ' aktiv)' : '')
                + ' — ' + total + ' GESAMT';
        }
    };

    // Liste im Dropdown rendern
    BlockState.prototype.renderPanelList = function() {
        var self = this;
        var list = self.el('cat-list');
        if (!list) return;

        var cats = self.filteredCats();

        if (!cats.length) {
            list.innerHTML = '<div class="text-muted small px-3 py-2">Keine Treffer.</div>';
            return;
        }

        // Gruppierung nach contextlabel
        var groups = {};
        var order  = [];
        cats.forEach(function(c) {
            var lbl = c.contextlabel || 'Allgemein';
            if (!groups[lbl]) { groups[lbl] = []; order.push(lbl); }
            groups[lbl].push(c);
        });

        var html = '';
        order.forEach(function(lbl) {
            // Gruppenheader
            html += '<div style="padding:4px 10px 2px;font-size:10px;color:#6c757d;'
                  + 'font-weight:600;text-transform:uppercase;letter-spacing:.04em;'
                  + 'border-top:1px solid #f0f0f0;margin-top:2px">'
                  + escHtml(lbl) + '</div>';
            groups[lbl].forEach(function(c) {
                var checked = self.cats[c.id] ? ' checked' : '';
                html += '<label style="display:flex;align-items:center;gap:8px;'
                      + 'padding:4px 12px;cursor:pointer;font-size:12px;'
                      + 'color:var(--bs-body-color,#212529)" '
                      + 'class="qf-cat-row' + (self.cats[c.id] ? ' qf-cat-row-active' : '') + '">'
                      + '<input type="checkbox" class="form-check-input qf-cat-chk" '
                      + 'style="margin:0;flex-shrink:0" data-catid="' + c.id + '"' + checked + '>'
                      + '<span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;'
                      + 'white-space:nowrap" title="' + escHtml(c.name) + '">'
                      + escHtml(c.name) + '</span>'
                      + '<span style="color:#adb5bd;font-size:10px;flex-shrink:0">'
                      + c.questioncount + '</span>'
                      + '</label>';
            });
        });

        list.innerHTML = html;

        // Checkbox-Handler
        list.querySelectorAll('.qf-cat-chk').forEach(function(chk) {
            chk.addEventListener('change', function(e) {
                e.stopPropagation();
                var id = parseInt(this.dataset.catid, 10);
                if (this.checked) self.cats[id] = true; else delete self.cats[id];
                // Zeile hervorheben
                var row = this.closest('.qf-cat-row');
                if (row) row.classList.toggle('qf-cat-row-active', !!this.checked);
                self.updateCatInput();
                self.triggerSearch();
            });
        });
    };

    // ---------------------------------------------------------------
    // Suchfeld parsen
    // ---------------------------------------------------------------
    BlockState.prototype.parseSearchInput = function(val) {
        var self  = this;
        var plain = val.trim();
        var hint  = self.el('search-hint');
        if (hint) {
            if (plain.charAt(0) === '#' && plain.length > 1) {
                hint.textContent = 'Enter: "' + plain.substring(1) + '" als Tag-Filter';
                hint.style.display = '';
            } else {
                hint.style.display = 'none';
            }
        }
        self.search = (plain.charAt(0) === '#') ? '' : plain;
        self.triggerSearch();
    };

    // ---------------------------------------------------------------
    // Fragetyp-Chips
    // ---------------------------------------------------------------
    BlockState.prototype.renderTypeChips = function() {
        var self = this;
        var wrap = self.el('type-chips');
        if (!wrap) return;
        var html = '';
        Object.keys(QTYPES).forEach(function(k) {
            html += '<button class="badge qf-chip qf-chip-type" data-type="' + k + '">'
                  + QTYPES[k] + '</button> ';
        });
        wrap.innerHTML = html;
        wrap.querySelectorAll('.qf-chip-type').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var t = this.dataset.type;
                if (self.types[t]) { delete self.types[t]; this.classList.remove('qf-chip-active'); }
                else               { self.types[t] = true;  this.classList.add('qf-chip-active'); }
                self.triggerSearch();
            });
        });
    };

    // ---------------------------------------------------------------
    // Tags
    // ---------------------------------------------------------------
    BlockState.prototype.addTag = function(val) {
        if (val && this.tags.indexOf(val) === -1) {
            this.tags.push(val);
            this.renderTagChips();
            this.triggerSearch();
        }
    };
    BlockState.prototype.removeTag = function(val) {
        this.tags = this.tags.filter(function(t) { return t !== val; });
        this.renderTagChips();
        this.triggerSearch();
    };
    BlockState.prototype.renderTagChips = function() {
        var self = this;
        var wrap = self.el('tag-chips');
        if (!wrap) return;
        if (!self.tags.length) { wrap.innerHTML = ''; return; }
        wrap.innerHTML = self.tags.map(function(t) {
            return '<span class="badge qf-chip qf-chip-tag qf-chip-active">#' + escHtml(t)
                 + ' <button class="qf-rm-tag" data-tag="' + escHtml(t)
                 + '" style="border:none;background:none;color:inherit;cursor:pointer;padding:0 0 0 2px">'
                 + '&times;</button></span> ';
        }).join('');
        wrap.querySelectorAll('.qf-rm-tag').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                self.removeTag(this.dataset.tag);
            });
        });
    };

    // ---------------------------------------------------------------
    // Suche
    // ---------------------------------------------------------------
    BlockState.prototype.triggerSearch = function() {
        var self = this;
        clearTimeout(self.debounce);
        self.debounce = setTimeout(function() { self.runSearch(); }, 350);
    };

    BlockState.prototype.runSearch = function() {
        var self    = this;
        var spinner = self.el('spinner');
        if (spinner) spinner.classList.remove('d-none');

        Ajax.call([{
            methodname: 'block_questionfilter_search_questions',
            args: {
                search:     self.search,
                categories: Object.keys(self.cats).join(','),
                types:      Object.keys(self.types).join(','),
                difficulty: '',
                tags:       Object.keys(self.diffs).concat(self.tags).join(','),
                scope:      self.config.searchscope || 'all',
                contextid:  self.config.contextid   || 1,
                limit:      200,
            },
            done: function(result) {
                if (spinner) spinner.classList.add('d-none');
                self.results = result.questions || [];
                var valid = {};
                self.results.forEach(function(q) { valid[q.id] = true; });
                Object.keys(self.selected).forEach(function(id) {
                    if (!valid[id]) delete self.selected[id];
                });
                self.renderResults();
                self.updateFooter();
            },
            fail: function(err) {
                if (spinner) spinner.classList.add('d-none');
                var list = self.el('results');
                if (list) list.innerHTML = '<div class="alert alert-warning small p-2">'
                    + (err.message || 'Suche fehlgeschlagen') + '</div>';
            },
        }]);
    };

    // ---------------------------------------------------------------
    // Ergebnisliste
    // ---------------------------------------------------------------
    BlockState.prototype.renderResults = function() {
        var self  = this;
        var wrap  = self.el('results');
        var count = self.el('count');
        if (!wrap) return;
        if (count) count.textContent = self.results.length
            + ' Frage' + (self.results.length !== 1 ? 'n' : '') + ' gefunden';
        if (!self.results.length) {
            wrap.innerHTML = '<div class="text-center text-muted small py-3">Keine Treffer.</div>';
            return;
        }
        var html = '';
        self.results.forEach(function(q) {
            var sel     = !!self.selected[q.id];
            var typeLbl = QTYPES[q.qtype] || q.qtype;
            var typeCol = TYPE_COLOR[q.qtype] || 'bg-secondary bg-opacity-10 text-secondary';
            var tagPills= (q.tags || []).map(function(t) {
                return '<span class="badge bg-primary bg-opacity-10 text-primary" style="font-size:10px">#'
                     + escHtml(t.name) + '</span>';
            }).join(' ');
            html += '<div class="qf-item p-1 mb-1 rounded d-flex gap-2 align-items-start'
                 + (sel ? ' qf-item-sel bg-info bg-opacity-10 border border-info' : '')
                 + '" data-qid="' + q.id + '" style="cursor:pointer">'
                 + '<input type="checkbox" class="form-check-input mt-1 flex-shrink-0 qf-chk"'
                 + (sel ? ' checked' : '') + ' data-qid="' + q.id + '">'
                 + '<div style="min-width:0;flex:1">'
                 + '<div class="small fw-semibold text-truncate" title="' + escHtml(q.name) + '">'
                 + escHtml(q.name) + '</div>'
                 + '<div class="d-flex flex-wrap gap-1 mt-1">'
                 + '<span class="badge ' + typeCol + '" style="font-size:10px">' + typeLbl + '</span>'
                 + '<span class="badge bg-light text-muted" style="font-size:10px">'
                 + escHtml(q.categoryname) + '</span>'
                 + tagPills + '</div></div></div>';
        });
        wrap.innerHTML = html;
        wrap.querySelectorAll('.qf-item').forEach(function(item) {
            item.addEventListener('click', function(e) {
                if (e.target.tagName === 'INPUT') return;
                self.toggleSelect(parseInt(this.dataset.qid, 10));
            });
        });
        wrap.querySelectorAll('.qf-chk').forEach(function(chk) {
            chk.addEventListener('change', function() {
                self.toggleSelect(parseInt(this.dataset.qid, 10));
            });
        });
    };

    BlockState.prototype.toggleSelect = function(id) {
        if (this.selected[id]) delete this.selected[id]; else this.selected[id] = true;
        this.renderResults(); this.updateFooter();
    };
    BlockState.prototype.toggleSelectAll = function() {
        var self = this;
        if (Object.keys(self.selected).length === self.results.length) self.selected = {};
        else self.results.forEach(function(q) { self.selected[q.id] = true; });
        self.renderResults(); self.updateFooter();
    };
    BlockState.prototype.updateFooter = function() {
        var n    = Object.keys(this.selected).length;
        var info = this.el('sel-info');
        if (info) info.textContent = n === 0 ? 'Nichts ausgewählt'
            : n + ' Frage' + (n !== 1 ? 'n' : '') + ' ausgewählt';
        this.block().querySelectorAll('.qf-export,.qf-add-to-quiz').forEach(function(b) {
            b.disabled = (n === 0);
        });
    };

    // ---------------------------------------------------------------
    // Export / Zum Test
    // ---------------------------------------------------------------
    BlockState.prototype.doExport = function(format) {
        var self = this;
        var ids  = Object.keys(self.selected).join(',');
        if (!ids) return;
        Ajax.call([{
            methodname: 'block_questionfilter_export_questions',
            args: { questionids: ids, format: format, contextid: self.config.contextid || 1 },
            done: function(result) {
                if (result.success && result.url) {
                    var a = document.createElement('a');
                    a.href = result.url; a.download = result.filename;
                    document.body.appendChild(a); a.click(); document.body.removeChild(a);
                }
            },
            fail: function(err) { alert('Export fehlgeschlagen: ' + (err.message || '')); },
        }]);
    };
    BlockState.prototype.addToQuiz = function() {
        var ids = Object.keys(this.selected);
        if (!ids.length) return;
        window.location.href = M.cfg.wwwroot
            + '/question/bank/managecategories/index.php?courseid=1&qids=' + ids.join(',');
    };

    function escHtml(s) {
        if (s == null) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    return {
        init: function(config) {
            var state = new BlockState(config.blockid, config);
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() { state.init(); });
            } else { state.init(); }
        },
    };
});
