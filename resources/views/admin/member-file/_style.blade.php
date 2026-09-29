<style>
/* one font everywhere (item 4): buttons / inputs / selects do not inherit the page font by default */
.mf-page button,.mf-page input,.mf-page select,.mf-page textarea{font-family:inherit;}
.mf-page{height:calc(100vh - 46px); overflow:hidden; padding:8px 16px; box-sizing:border-box; display:flex; flex-direction:column; gap:7px;}
.mf-title{font-size:13px; font-weight:700; color:#263238; white-space:nowrap;}
.mf-sub{font-size:9px; color:#6b7280;}
.mf-box{flex:1; min-height:0; overflow:hidden; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 12px; box-sizing:border-box;}
.mf-btn{background:#1565C0; color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; white-space:nowrap;}
.mf-btn-sq{background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block;}
.mf-bar{flex-shrink:0; display:flex; align-items:center; justify-content:space-between; padding:6px 12px; background:#fff; border:1px solid #d1d5db; border-radius:8px;}
.mf-grid{display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:8px 14px; align-content:start;}
.mf-f{display:flex; flex-direction:column; gap:3px; min-width:0;}
.mf-f label{font-size:8.5px; font-weight:700; color:#6b7280; text-transform:uppercase; white-space:nowrap;}
.mf-f input,.mf-f select,.mf-f textarea{font-family:inherit; font-size:11px; color:#263238; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; box-sizing:border-box; width:100%; background:#fff;}
.mf-err{font-size:9px; color:#c62828; font-weight:600;}
.mf-ok{flex-shrink:0; font-size:9.5px; color:#2e7d32; font-weight:700;}
.mf-steps{display:flex; gap:2px 6px; flex-wrap:wrap; white-space:nowrap; font-size:9.5px; font-weight:700; color:#94A3B8;}
.mf-steps span.on{color:#1565C0; border-bottom:2px solid #1565C0;}
.mf-table{width:100%; border-collapse:collapse; font-size:11px; color:#263238;}
.mf-table th{background:#f0f9ff; color:#374151; font-size:0.85em; font-weight:700; text-align:left; padding:0.4em 0.6em; border-bottom:1px solid #d1d5db; white-space:nowrap;}
.mf-table td{padding:0.4em 0.6em; border-bottom:1px solid #f3f4f6; white-space:nowrap;}
.mf-table td.wrap{white-space:normal; min-width:9em;}
.mf-tag{display:inline-block; font-size:0.82em; font-weight:700; color:#fff; background:#1565C0; border-radius:10px; padding:1px 7px; margin:1px 2px 1px 0; cursor:pointer;}
.mf-tag.pr{background:#6b7280; cursor:default;}
.mf-fee{font-size:0.82em; font-weight:700;}
.mf-fee.OVERDUE{color:#c62828;} .mf-fee.DUE{color:#b26a00;} .mf-fee.PAID,.mf-fee.FREE,.mf-fee.WAIVED{color:#2e7d32;}
</style>
<script>
// no scroll / no hidden data: a table marked .mf-fit shrinks its font (together) until it fits its box
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('table.mf-fit').forEach(function(t){
        var box = t.parentNode, fs = parseFloat(getComputedStyle(t).fontSize) || 11;
        while ((t.scrollWidth > box.clientWidth + 1 || t.getBoundingClientRect().bottom > box.getBoundingClientRect().bottom - 2) && fs > 6.5) { fs -= 0.25; t.style.fontSize = fs + 'px'; }
    });
});
</script>
