<?php
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$lang = I18n::getCurrentLang();
Layout::head(__('locations_title'));
?>
<body>
<?php Layout::sidebar('locations'); Layout::beginContent(); ?>
<?php Layout::pageHeader(__('locations_title'), 'fas fa-map-marker-alt', __('locations_hierarchy').': '.__('locations_building').' → '.__('locations_room').' → '.__('locations_cabinet').' → '.__('locations_shelf').' → '.__('locations_slot')); ?>

<div id="labCards" class="ci-auto-grid" style="margin-bottom:16px">
    <div class="ci-card ci-card-body" style="height:140px;background:#f9f9f9"></div>
    <div class="ci-card ci-card-body" style="height:140px;background:#f9f9f9"></div>
    <div class="ci-card ci-card-body" style="height:140px;background:#f9f9f9"></div>
</div>

<div id="hierarchySection" style="display:none">
    <div class="ci-card">
        <div class="ci-card-head">
            <span><i class="fas fa-sitemap" style="color:var(--accent)"></i> <span id="hierarchyTitle"></span></span>
            <button onclick="backToLabs()" class="ci-btn ci-btn-secondary ci-btn-sm"><i class="fas fa-arrow-left"></i> <?php echo __('back'); ?></button>
        </div>
    </div>
    <div id="hierarchyTree" style="margin-top:12px"></div>
</div>

<?php Layout::endContent(); ?>
<style>
@media(max-width:480px){
    #hierarchyTree [style*="margin-left:20px"]{margin-left:10px!important;padding-left:8px!important}
}
</style>
<script>
const LANG='<?php echo $lang; ?>';
const t={building:'<?php echo __("locations_building"); ?>',room:'<?php echo __("locations_room"); ?>',cabinet:'<?php echo __("locations_cabinet"); ?>',shelf:'<?php echo __("locations_shelf"); ?>',containers:'<?php echo __("nav_containers"); ?>',no_data:'<?php echo __("no_data"); ?>',members:'<?php echo $lang==="th"?"สมาชิก":"Members"; ?>'};

async function loadLabs(){
    try{
        const d=await apiFetch('/v1/api/dashboard.php');
        if(d.success&&d.data){
            const labs=d.data.labs||[];
            const el=document.getElementById('labCards');
            if(!labs.length){el.innerHTML=`<div style="grid-column:1/-1" class="ci-empty"><i class="fas fa-building"></i><p>${t.no_data}</p></div>`;return}
            el.innerHTML=labs.map(l=>`
                <div class="ci-card" style="cursor:pointer;transition:box-shadow .15s" onclick="loadHierarchy(${l.id},'${escapeHtml(l.name)}')" onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,.08)'" onmouseout="this.style.boxShadow='none'">
                    <div class="ci-card-body">
                        <div style="display:flex;gap:12px;align-items:center;margin-bottom:14px">
                            <div class="ci-stat-icon green" style="width:44px;height:44px"><i class="fas fa-building"></i></div>
                            <div><h3 style="font-weight:600;font-size:14px;color:#333">${l.name}</h3><p style="font-size:11px;color:#aaa">${l.code||''}</p></div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                            <div style="background:#f7f7f7;border-radius:6px;padding:10px;text-align:center"><p style="font-size:20px;font-weight:700;color:var(--accent)">${l.container_count||0}</p><p style="font-size:11px;color:#999">${t.containers}</p></div>
                            <div style="background:#f7f7f7;border-radius:6px;padding:10px;text-align:center"><p style="font-size:20px;font-weight:700;color:var(--accent)">${l.member_count||0}</p><p style="font-size:11px;color:#999">${t.members}</p></div>
                        </div>
                    </div>
                </div>`).join('');
        }
    }catch(e){console.error(e)}
}

async function loadHierarchy(labId,labName){
    document.getElementById('labCards').style.display='none';
    document.getElementById('hierarchySection').style.display='block';
    document.getElementById('hierarchyTitle').textContent=labName;
    const tree=document.getElementById('hierarchyTree');
    tree.innerHTML='<div class="ci-loading"><div class="ci-spinner"></div></div>';
    try{
        const d=await apiFetch('/v1/api/locations.php?hierarchy=1&lab_id='+labId);
        if(d.success&&d.data.length){
            tree.innerHTML=d.data.map(b=>`
                <div class="ci-card" style="margin-bottom:8px">
                    <div class="ci-card-body">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                            <i class="fas fa-building" style="color:var(--accent)"></i>
                            <strong>${b.name}</strong>
                            <span class="ci-badge ci-badge-info">${t.building}</span>
                        </div>
                        ${(b.rooms||[]).map(r=>`
                            <div style="margin-left:20px;border-left:2px solid #eee;padding-left:14px;margin-bottom:10px">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                                    <i class="fas fa-door-open" style="color:var(--warn)"></i>
                                    <span style="font-weight:500;font-size:13px">${r.name}</span>
                                    <span class="ci-badge ci-badge-warning">${t.room}</span>
                                </div>
                                ${(r.cabinets||[]).map(c=>`
                                    <div style="margin-left:20px;border-left:2px solid #eee;padding-left:14px;margin-bottom:6px">
                                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                                            <i class="fas fa-archive" style="color:#7b1fa2"></i>
                                            <span style="font-size:13px">${c.name}</span>
                                            <span style="font-size:11px;color:#999">(${c.container_count||0} ${t.containers})</span>
                                        </div>
                                        ${(c.shelves||[]).map(s=>`
                                            <div style="margin-left:20px;border-left:1px solid #eee;padding-left:14px;margin-bottom:3px">
                                                <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:#666">
                                                    <i class="fas fa-layer-group" style="color:var(--accent)"></i> ${s.name} <span style="font-size:11px;color:#aaa">(${s.container_count||0})</span>
                                                </div>
                                            </div>`).join('')}
                                    </div>`).join('')}
                            </div>`).join('')}
                    </div>
                </div>`).join('');
        }else{tree.innerHTML=`<div class="ci-empty"><i class="fas fa-sitemap"></i><p>${t.no_data}</p></div>`}
    }catch(e){tree.innerHTML=`<div class="ci-empty"><i class="fas fa-sitemap"></i><p>${t.no_data}</p></div>`}
}
function backToLabs(){document.getElementById('labCards').style.display='grid';document.getElementById('hierarchySection').style.display='none'}
function escapeHtml(t){const d=document.createElement('div');d.textContent=t;return d.innerHTML}
loadLabs();
</script>
</body></html>
