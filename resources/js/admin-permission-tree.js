/**
 * درخت دسترسی‌های کاربر ادمین — بدون وابستگی خارجی.
 */
(function () {
    var root = document.getElementById('au-perm-root');
    if (!root) return;

    var readonly = root.getAttribute('data-readonly') === '1';
    var assignable = [];
    try {
        assignable = JSON.parse(root.getAttribute('data-assignable') || '[]');
    } catch (e) {
        assignable = [];
    }
    var assignableSet = {};
    assignable.forEach(function (k) {
        assignableSet[k] = true;
    });

    function nodeList() {
        return Array.from(root.querySelectorAll('.au-perm-node'));
    }

    function checkboxesInNode(node) {
        return Array.from(node.querySelectorAll(':scope > .au-perm-node-row [data-perm-checkbox]'))
            .concat(Array.from(node.querySelectorAll(':scope > .au-perm-children [data-perm-checkbox]')));
    }

    function childCheckboxes(node) {
        var children = node.querySelector(':scope > .au-perm-children');
        if (!children) return [];
        return Array.from(children.querySelectorAll('[data-perm-checkbox]'));
    }

    function directCheckbox(node) {
        return node.querySelector(':scope > .au-perm-node-row [data-perm-checkbox]');
    }

    function setChecked(cb, on) {
        if (!cb || cb.disabled) return;
        cb.checked = !!on;
        cb.indeterminate = false;
    }

    function updateParentState(node) {
        var parentCb = directCheckbox(node);
        var kids = childCheckboxes(node);
        if (!parentCb || kids.length === 0) return;

        var enabled = kids.filter(function (c) {
            return !c.disabled;
        });
        if (enabled.length === 0) return;

        var checked = enabled.filter(function (c) {
            return c.checked;
        });
        if (checked.length === 0) {
            parentCb.checked = false;
            parentCb.indeterminate = false;
        } else if (checked.length === enabled.length) {
            parentCb.checked = true;
            parentCb.indeterminate = false;
        } else {
            parentCb.checked = false;
            parentCb.indeterminate = true;
        }
    }

    function cascadeDown(node, on) {
        var parentCb = directCheckbox(node);
        if (parentCb && !parentCb.disabled) {
            setChecked(parentCb, on);
        }
        childCheckboxes(node).forEach(function (cb) {
            setChecked(cb, on);
        });
    }

    function refreshAllParents() {
        nodeList()
            .slice()
            .reverse()
            .forEach(updateParentState);
    }

    root.addEventListener('change', function (e) {
        var cb = e.target;
        if (!(cb instanceof HTMLInputElement) || !cb.matches('[data-perm-checkbox]') || readonly) {
            return;
        }
        var node = cb.closest('.au-perm-node');
        if (!node) return;

        if (childCheckboxes(node).length > 0) {
            cascadeDown(node, cb.checked);
        }

        refreshAllParents();
    });

    root.querySelectorAll('.au-perm-toggle').forEach(function (btn) {
        if (btn.classList.contains('au-perm-toggle--spacer')) return;
        btn.addEventListener('click', function () {
            var node = btn.closest('.au-perm-node');
            if (!node) return;
            var children = node.querySelector(':scope > .au-perm-children');
            if (!children) return;
            var open = children.hidden;
            children.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            var ico = btn.querySelector('i');
            if (ico) {
                ico.className = open ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-left';
            }
        });
    });

    var search = document.getElementById('au-perm-search');
    if (search) {
        search.addEventListener('input', function () {
            var q = (search.value || '').trim().toLowerCase();
            nodeList().forEach(function (node) {
                var label = node.getAttribute('data-perm-label') || '';
                var match = q === '' || label.indexOf(q) !== -1;
                var childMatch = false;
                if (!match && q !== '') {
                    node.querySelectorAll('.au-perm-node').forEach(function (sub) {
                        if (sub === node) return;
                        var sl = sub.getAttribute('data-perm-label') || '';
                        if (sl.indexOf(q) !== -1) childMatch = true;
                    });
                }
                var show = match || childMatch;
                node.classList.toggle('au-perm-node--hidden', !show);
                if (show && q !== '') {
                    var ch = node.querySelector(':scope > .au-perm-children');
                    if (ch) ch.hidden = false;
                }
            });
        });
    }

    function forEachEnabledCheckbox(fn) {
        root.querySelectorAll('[data-perm-checkbox]').forEach(function (cb) {
            if (!(cb instanceof HTMLInputElement) || cb.disabled) return;
            fn(cb);
        });
    }

    document.getElementById('au-perm-select-all')?.addEventListener('click', function () {
        if (readonly) return;
        forEachEnabledCheckbox(function (cb) {
            setChecked(cb, true);
        });
        refreshAllParents();
    });

    document.getElementById('au-perm-clear-all')?.addEventListener('click', function () {
        if (readonly) return;
        forEachEnabledCheckbox(function (cb) {
            setChecked(cb, false);
        });
        refreshAllParents();
    });

    document.getElementById('au-perm-expand-all')?.addEventListener('click', function () {
        root.querySelectorAll('.au-perm-children').forEach(function (el) {
            el.hidden = false;
        });
    });

    document.getElementById('au-perm-collapse-all')?.addEventListener('click', function () {
        root.querySelectorAll('.au-perm-children').forEach(function (el) {
            el.hidden = true;
        });
    });

    window.auPermTreeApplyKeys = function (keys) {
        var set = {};
        (keys || []).forEach(function (k) {
            set[k] = true;
        });
        root.querySelectorAll('[data-perm-checkbox]').forEach(function (cb) {
            if (!(cb instanceof HTMLInputElement)) return;
            setChecked(cb, !!set[cb.value]);
        });
        refreshAllParents();
    };

    window.auPermTreeSetReadonly = function (on) {
        readonly = !!on;
        root.querySelectorAll('[data-perm-checkbox]').forEach(function (cb) {
            if (!(cb instanceof HTMLInputElement)) return;
            if (on) {
                cb.disabled = true;
            } else {
                var can = assignable.length === 0 || assignableSet[cb.value];
                cb.disabled = !can;
            }
        });
        refreshAllParents();
    };

    refreshAllParents();
})();
