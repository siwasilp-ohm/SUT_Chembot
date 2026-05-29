<?php
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$lang = I18n::getCurrentLang();
$userId = $user['id'];
Layout::head(__('borrow_title'));
?>
<body>
<?php Layout::sidebar('borrow'); Layout::beginContent(); ?>
<?php Layout::pageHeader(__('borrow_title'), 'fas fa-exchange-alt'); ?>

<div class="ci-tabs">
    <button onclick="showTab('pending')" id="tab-pending" class="ci-tab active"><?php echo __('borrow_pending'); ?></button>
    <button onclick="showTab('active')" id="tab-active" class="ci-tab"><?php echo __('borrow_approved'); ?></button>
    <button onclick="showTab('history')" id="tab-history" class="ci-tab"><?php echo __('borrow_history'); ?></button>
</div>

<div id="borrowList"><div class="ci-loading"><div class="ci-spinner"></div></div></div>

<div id="emptyState" style="display:none" class="ci-empty">
    <i class="fas fa-hand-holding-medical"></i>
    <p id="emptyTitle" style="font-size:15px;font-weight:500;margin-bottom:4px"></p>
    <p><?php echo $lang==='th'?'คำขอยืมจะปรากฏที่นี่':'Borrow requests will appear here'; ?></p>
</div>

<?php Layout::endContent(); ?>
<style>
@media(max-width:480px){
    .ci-card-body [style*="display:flex"][style*="justify-content:space-between"]{flex-direction:column!important;gap:8px!important}
    .ci-card-body [style*="display:flex"][style*="justify-content:space-between"] [style*="text-align:right"]{text-align:left!important}
}
</style>
<script>
const LANG='<?php echo $lang; ?>',UID=<?php echo $userId; ?>;
const t={pending:'<?php echo __("borrow_pending"); ?>',active:'<?php echo __("borrow_approved"); ?>',history:'<?php echo __("borrow_history"); ?>',approve:'<?php echo __("borrow_approve"); ?>',reject:'<?php echo __("borrow_reject"); ?>',qty:'<?php echo __("borrow_quantity"); ?>',purpose:'<?php echo __("borrow_purpose"); ?>',requester:'<?php echo __("borrow_requester"); ?>',no_data:'<?php echo __("no_data"); ?>'};
let currentTab='pending';

function showTab(tab){
    currentTab=tab;
    document.querySelectorAll('.ci-tab').forEach(el=>el.classList.remove('active'));
    document.getElementById('tab-'+tab).classList.add('active');
    load();
}

async function load(){
    const list=document.getElementById('borrowList'),empty=document.getElementById('emptyState');
    list.innerHTML='<div class="ci-loading"><div class="ci-spinner"></div></div>';
    empty.style.display='none';
    try{
        const statusMap={pending:'pending',active:'approved,fulfilled',history:'returned,rejected,cancelled'};
        const d=await apiFetch('/v1/api/borrow.php?status='+statusMap[currentTab]);
        if(d.success){
            const items=d.data.data||d.data||[];
            if(!items.length){list.innerHTML='';empty.style.display='block';document.getElementById('emptyTitle').textContent=t[currentTab]+' - '+t.no_data;return}
            list.innerHTML=items.map(r=>{
                const canApprove=r.status==='pending'&&(r.owner_id==UID);
                return`<div class="ci-card ci-fade">
                    <div class="ci-card-body" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                        <div style="display:flex;gap:12px;align-items:start">
                            <div class="ci-stat-icon orange" style="width:36px;height:36px;font-size:14px;flex-shrink:0"><i class="fas fa-hand-holding-medical"></i></div>
                            <div>
                                <h3 style="font-weight:600;font-size:14px;color:#333">${r.request_number||'#'+r.id}</h3>
                                <p style="font-size:13px;color:#666;margin-top:2px">${r.chemical_name||''}</p>
                                <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:6px;font-size:12px;color:#999">
                                    <span><i class="fas fa-flask"></i> ${t.qty}: ${r.requested_quantity} ${r.quantity_unit||''}</span>
                                    ${r.purpose?`<span><i class="fas fa-clipboard"></i> ${r.purpose}</span>`:''}
                                    ${r.requester_name?`<span><i class="fas fa-user"></i> ${r.requester_name}</span>`:''}
                                </div>
                            </div>
                        </div>
                        <div style="text-align:right;flex-shrink:0">
                            ${statusBadge(r.status||'pending')}
                            <p style="font-size:11px;color:#aaa;margin-top:6px">${formatDate(r.created_at)}</p>
                        </div>
                    </div>
                    ${canApprove?`<div style="padding:8px 16px;border-top:1px solid #f0f0f0;display:flex;gap:8px">
                        <button onclick="handleReq(${r.id},'approve')" class="ci-btn ci-btn-primary ci-btn-sm"><i class="fas fa-check"></i> ${t.approve}</button>
                        <button onclick="handleReq(${r.id},'reject')" class="ci-btn ci-btn-danger ci-btn-sm"><i class="fas fa-times"></i> ${t.reject}</button>
                    </div>`:''}
                </div>`}).join('');
        }
    }catch(e){list.innerHTML='';empty.style.display='block';document.getElementById('emptyTitle').textContent=t.no_data}
}

async function handleReq(id,action){
    try{const d=await apiFetch('/v1/api/borrow.php?id='+id,{method:'PUT',body:JSON.stringify({action})});if(d.success)load();else alert(d.error||'Failed')}catch(e){alert('Error')}
}
load();
</script>
</body></html>
