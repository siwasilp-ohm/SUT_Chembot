<?php
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$lang = I18n::getCurrentLang();
Layout::head(__('chemicals_title'));
?>
<body>
<?php Layout::sidebar('chemicals'); Layout::beginContent(); ?>

<?php Layout::pageHeader(__('chemicals_title'), 'fas fa-flask', __('chemicals_search')); ?>

<div class="ci-card" style="margin-bottom:16px">
    <div class="ci-card-body ci-filter-bar">
        <div style="flex:1;min-width:200px;position:relative">
            <i class="fas fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#bbb;font-size:13px"></i>
            <input type="text" id="searchInput" class="ci-input" style="padding-left:32px" placeholder="<?php echo __('chemicals_search'); ?>">
        </div>
        <select id="categoryFilter" class="ci-select" style="width:160px"><option value=""><?php echo __('all'); ?> <?php echo __('chemicals_category'); ?></option></select>
        <select id="stateFilter" class="ci-select" style="width:140px">
            <option value=""><?php echo __('all'); ?> <?php echo __('chemicals_state'); ?></option>
            <option value="solid"><?php echo __('state_solid'); ?></option>
            <option value="liquid"><?php echo __('state_liquid'); ?></option>
            <option value="gas"><?php echo __('state_gas'); ?></option>
        </select>
    </div>
</div>

<div id="chemicalsList" class="ci-auto-grid">
    <div class="ci-card ci-card-body" style="height:160px;background:#f9f9f9"></div>
    <div class="ci-card ci-card-body" style="height:160px;background:#f9f9f9"></div>
    <div class="ci-card ci-card-body" style="height:160px;background:#f9f9f9"></div>
</div>

<div id="pagination" class="ci-pagination" style="display:none">
    <span id="showingText"></span>
    <div class="ci-pagination-btns">
        <button id="prevBtn" class="ci-btn ci-btn-secondary ci-btn-sm" disabled><i class="fas fa-chevron-left"></i> <?php echo __('previous'); ?></button>
        <button id="nextBtn" class="ci-btn ci-btn-secondary ci-btn-sm"><?php echo __('next'); ?> <i class="fas fa-chevron-right"></i></button>
    </div>
</div>

<!-- Detail Modal -->
<div id="detailModal" class="ci-modal-bg">
    <div class="ci-modal">
        <div class="ci-modal-hdr"><h3 id="modalTitle"></h3><button class="ci-modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button></div>
        <div class="ci-modal-body" id="modalContent"></div>
    </div>
</div>

<?php Layout::endContent(); ?>
<style>
@media(max-width:480px){
    .ci-detail-grid{grid-template-columns:1fr!important}
}
</style>
<script>
const LANG='<?php echo $lang; ?>';
let chemicals=[],page=1,perPage=12,total=0;
const t={no_results:'<?php echo __("chemicals_no_results"); ?>',cas:'<?php echo __("chemicals_cas"); ?>',formula:'<?php echo __("chemicals_formula"); ?>',category:'<?php echo __("chemicals_category"); ?>',state:'<?php echo __("chemicals_state"); ?>',containers:'<?php echo __("chemicals_containers"); ?>',mw:'<?php echo __("chemicals_molecular_weight"); ?>',hazard:'<?php echo __("chemicals_hazard"); ?>',signal:'<?php echo __("chemicals_signal_word"); ?>',desc:'<?php echo __("chemicals_description"); ?>',showing:'<?php echo __("showing"); ?>',of:'<?php echo __("of"); ?>',items:'<?php echo __("items"); ?>'};

async function loadChemicals(){
    try{
        const params=new URLSearchParams({page,limit:perPage});
        const s=document.getElementById('searchInput').value,cat=document.getElementById('categoryFilter').value,st=document.getElementById('stateFilter').value;
        if(s)params.set('search',s);if(cat)params.set('category',cat);if(st)params.set('state',st);
        const data=await apiFetch('/v1/api/chemicals.php?'+params);
        if(data.success){chemicals=data.data.chemicals||data.data||[];total=data.data.total||chemicals.length;render()}
    }catch(e){console.error(e)}
}
function render(){
    const el=document.getElementById('chemicalsList');
    if(!chemicals.length){el.innerHTML=`<div style="grid-column:1/-1" class="ci-empty"><i class="fas fa-flask"></i><p>${t.no_results}</p></div>`;return}
    el.innerHTML=chemicals.map(c=>{
        const stIcons={solid:'fa-cube',liquid:'fa-tint',gas:'fa-wind'};
        const signalCls=c.signal_word==='Danger'?'ci-badge-danger':c.signal_word==='Warning'?'ci-badge-warning':'ci-badge-default';
        return`<div class="ci-card" style="cursor:pointer;transition:box-shadow .15s" onclick="showDetail(${c.id})" onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,.08)'" onmouseout="this.style.boxShadow='none'">
            <div class="ci-card-body">
                <div style="display:flex;justify-content:space-between;margin-bottom:10px">
                    <div class="ci-stat-icon blue" style="width:36px;height:36px;font-size:14px"><i class="fas fa-flask"></i></div>
                    ${c.signal_word?`<span class="ci-badge ${signalCls}">${c.signal_word}</span>`:''}
                </div>
                <h3 style="font-weight:600;font-size:14px;color:#333;margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${c.name||''}</h3>
                <p style="font-size:12px;color:#999;margin-bottom:10px">${c.molecular_formula||''}</p>
                <div style="font-size:12px;color:#777;display:flex;flex-direction:column;gap:4px">
                    ${c.cas_number?`<div><span style="color:#aaa;width:36px;display:inline-block">CAS</span> <span style="font-family:monospace">${c.cas_number}</span></div>`:''}
                    <div><i class="fas ${stIcons[c.physical_state]||'fa-atom'}" style="width:14px;text-align:center"></i> ${c.physical_state||'-'}</div>
                    ${c.category_name?`<div><i class="fas fa-tag" style="width:14px;text-align:center;color:#aaa"></i> ${c.category_name}</div>`:''}
                </div>
                <div style="margin-top:10px;padding-top:10px;border-top:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center;font-size:12px;color:#aaa">
                    <span><i class="fas fa-box"></i> ${c.container_count||0} ${t.containers}</span>
                    <i class="fas fa-chevron-right" style="font-size:10px"></i>
                </div>
            </div>
        </div>`}).join('');
    const pgEl=document.getElementById('pagination'),tp=Math.ceil(total/perPage);
    if(tp>1){pgEl.style.display='flex';document.getElementById('showingText').textContent=`${t.showing} ${(page-1)*perPage+1}-${Math.min(page*perPage,total)} ${t.of} ${total} ${t.items}`;document.getElementById('prevBtn').disabled=page<=1;document.getElementById('nextBtn').disabled=page>=tp}else{pgEl.style.display='none'}
}
function showDetail(id){
    const c=chemicals.find(x=>x.id==id);if(!c)return;
    document.getElementById('modalTitle').textContent=c.name||'';
    const ghsP=(c.ghs_pictograms||'').split(',').filter(Boolean);
    document.getElementById('modalContent').innerHTML=`
        <div class="ci-detail-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:13px">
            <div style="display:flex;flex-direction:column;gap:12px">
                ${fld(t.cas,c.cas_number)}${fld(t.formula,c.molecular_formula)}${fld(t.mw,c.molecular_weight?c.molecular_weight+' g/mol':'')}${fld(t.state,c.physical_state)}${fld(t.category,c.category_name)}
            </div>
            <div style="display:flex;flex-direction:column;gap:12px">
                ${c.signal_word?`<div><span style="color:#999">${t.signal}</span><div style="margin-top:4px"><span class="ci-badge ${c.signal_word==='Danger'?'ci-badge-danger':'ci-badge-warning'}">${c.signal_word}</span></div></div>`:''}
                ${c.hazard_statements?`<div><span style="color:#999">${t.hazard}</span><p style="margin-top:4px">${c.hazard_statements}</p></div>`:''}
                ${ghsP.length?`<div style="display:flex;gap:6px">${ghsP.map(p=>`<span style="width:36px;height:36px;background:#fff5f5;border:1px solid #fecaca;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#dc2626">${p}</span>`).join('')}</div>`:''}
            </div>
        </div>
        ${c.description?`<div style="margin-top:16px;padding-top:16px;border-top:1px solid #eee"><span style="font-size:13px;color:#999">${t.desc}</span><p style="margin-top:4px;font-size:13px">${c.description}</p></div>`:''}`;
    document.getElementById('detailModal').classList.add('show');
}
function fld(l,v){return v?`<div><span style="color:#999">${l}</span><p style="font-weight:500;color:#333">${v}</p></div>`:''}
function closeModal(){document.getElementById('detailModal').classList.remove('show')}
document.getElementById('detailModal').addEventListener('click',e=>{if(e.target===e.currentTarget)closeModal()});
let st;document.getElementById('searchInput').addEventListener('input',()=>{clearTimeout(st);st=setTimeout(()=>{page=1;loadChemicals()},300)});
document.getElementById('categoryFilter').addEventListener('change',()=>{page=1;loadChemicals()});
document.getElementById('stateFilter').addEventListener('change',()=>{page=1;loadChemicals()});
document.getElementById('prevBtn').addEventListener('click',()=>{page--;loadChemicals()});
document.getElementById('nextBtn').addEventListener('click',()=>{page++;loadChemicals()});
apiFetch('/v1/api/chemicals.php?categories=1').then(d=>{if(d.success&&d.data){const sel=document.getElementById('categoryFilter');(d.data.categories||d.data||[]).forEach(c=>{const o=document.createElement('option');o.value=c.id;o.textContent=c.name;sel.appendChild(o)})}}).catch(()=>{});
loadChemicals();
</script>
</body></html>
