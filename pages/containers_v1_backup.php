<?php
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$lang = I18n::getCurrentLang();
$action = $_GET['action'] ?? '';
$displayName = !empty($user['full_name_th']) ? htmlspecialchars($user['full_name_th']) : htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
Layout::head(__('containers_title'));
?>
<style>
.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:8px}
.form-row-3{grid-template-columns:repeat(3,1fr)}
.form-row-2{grid-template-columns:1fr 1fr}
.ci-fg label{font-size:12px;font-weight:600;color:var(--c2);margin-bottom:3px;display:block}
.ci-fg .req::after{content:' *';color:var(--danger)}
.ci-hint{font-size:11px;color:#aaa;margin:2px 0 0}
.form-preview{background:#f8faf9;border:2px dashed var(--accent-l);border-radius:8px;padding:16px;margin-top:16px}
.form-preview h3{font-size:14px;font-weight:600;color:var(--c1);margin-bottom:12px;display:flex;align-items:center;gap:6px}
.preview-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;font-size:12px}
.preview-item{display:flex;flex-direction:column;gap:2px}
.preview-label{color:var(--c3);font-size:11px}
.preview-value{font-weight:600;color:var(--c1);word-break:break-all}
.submit-bar{display:flex;gap:10px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--border)}
.bottle-card{transition:all .15s}
.bottle-card:hover{box-shadow:0 3px 12px rgba(0,0,0,.08);transform:translateY(-1px)}
.bottle-id{font-family:monospace;font-size:11px;color:var(--accent);background:var(--accent-l);padding:2px 6px;border-radius:3px;display:inline-block}
.mfr-tag{font-size:10px;background:#e3f2fd;color:#1565c0;padding:1px 6px;border-radius:3px;display:inline-flex;align-items:center;gap:3px}
.step-indicator{display:flex;gap:4px;margin-bottom:20px}
.step-dot{width:8px;height:8px;border-radius:50%;background:var(--border)}
.step-dot.active{background:var(--accent);box-shadow:0 0 0 3px var(--accent-l)}
.step-dot.done{background:var(--accent)}
@media(max-width:768px){
    .form-row,.form-row-3{grid-template-columns:1fr}
    .form-row-2{grid-template-columns:1fr}
    .submit-bar{flex-direction:column}
    .submit-bar .ci-btn{width:100%;justify-content:center}
    .preview-grid{grid-template-columns:1fr 1fr}
}
@media(max-width:480px){.preview-grid{grid-template-columns:1fr}}
</style>
<body>
<?php Layout::sidebar('containers'); Layout::beginContent(); ?>

<?php if ($action === 'add'): ?>
<!-- ═══ ADD BOTTLE FORM ═══ -->
<?php Layout::pageHeader(
    $lang==='th'?'เพิ่มขวดสาร':'Add Chemical Bottle', 
    'fas fa-plus-circle', 
    $lang==='th'?'บันทึกข้อมูลขวดสารเคมีเข้าคลัง':'Register a new chemical bottle into inventory'
); ?>

<div style="max-width:900px">
<div class="step-indicator">
    <div class="step-dot active" title="Chemical"></div>
    <div class="step-dot" title="Bottle"></div>
    <div class="step-dot" title="Location"></div>
    <div class="step-dot" title="Purchase"></div>
</div>
<form id="addBottleForm">
    <!-- Section 1: Chemical Info -->
    <div class="ci-card">
        <div class="ci-card-head"><span><i class="fas fa-flask" style="color:var(--accent)"></i> <?php echo $lang==='th'?'ข้อมูลสารเคมี':'Chemical Info'; ?></span></div>
        <div class="ci-card-body">
            <div class="form-row form-row-2">
                <div class="ci-fg">
                    <label class="req"><?php echo $lang==='th'?'ชื่อสารเคมี':'Chemical Name'; ?></label>
                    <input type="text" id="chemName" class="ci-input" placeholder="<?php echo $lang==='th'?'เช่น Carbon dioxide, TAE Buffer 50X':'e.g. Carbon dioxide'; ?>" required list="chemSuggestions" autocomplete="off">
                    <datalist id="chemSuggestions"></datalist>
                </div>
                <div class="ci-fg">
                    <label>CAS / Catalogue No.</label>
                    <input type="text" id="casNumber" class="ci-input" placeholder="<?php echo $lang==='th'?'เช่น 124-38-9':'e.g. 124-38-9'; ?>">
                </div>
            </div>
            <div class="form-row form-row-3">
                <div class="ci-fg">
                    <label><?php echo $lang==='th'?'เกรด':'Grade'; ?></label>
                    <select id="gradeSelect" class="ci-select">
                        <option value="">—</option>
                        <option value="ACS Grade">ACS Grade</option>
                        <option value="Analytical Grade">Analytical Grade</option>
                        <option value="Industrial Grade">Industrial Grade</option>
                        <option value="Lab Grade">Lab Grade</option>
                        <option value="Molecular Biology Grade">Molecular Biology Grade</option>
                        <option value="HPLC Grade">HPLC Grade</option>
                        <option value="Reagent Grade">Reagent Grade</option>
                        <option value="Technical Grade">Technical Grade</option>
                        <option value="Food Grade">Food Grade</option>
                    </select>
                </div>
                <div class="ci-fg">
                    <label><?php echo $lang==='th'?'ผู้ผลิต':'Manufacturer'; ?></label>
                    <input type="text" id="manufacturer" class="ci-input" placeholder="<?php echo $lang==='th'?'เช่น Air Liquid, Vivantis':'e.g. Air Liquid'; ?>" list="mfrSuggestions" autocomplete="off">
                    <datalist id="mfrSuggestions"></datalist>
                </div>
                <div class="ci-fg">
                    <label><?php echo $lang==='th'?'สถานะทางกายภาพ':'Physical State'; ?></label>
                    <select id="physicalState" class="ci-select">
                        <option value="liquid"><?php echo $lang==='th'?'ของเหลว':'Liquid'; ?></option>
                        <option value="solid"><?php echo $lang==='th'?'ของแข็ง':'Solid'; ?></option>
                        <option value="gas"><?php echo $lang==='th'?'แก๊ส':'Gas'; ?></option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: Bottle/Container Info -->
    <div class="ci-card">
        <div class="ci-card-head"><span><i class="fas fa-box" style="color:#e65100"></i> <?php echo $lang==='th'?'ข้อมูลขวด/บรรจุภัณฑ์':'Bottle / Container Info'; ?></span></div>
        <div class="ci-card-body">
            <div class="form-row form-row-3">
                <div class="ci-fg">
                    <label><?php echo $lang==='th'?'รหัสขวด':'Bottle Code'; ?></label>
                    <input type="text" id="bottleCode" class="ci-input" placeholder="<?php echo $lang==='th'?'อัตโนมัติถ้าว่าง':'Auto if empty'; ?>">
                    <p class="ci-hint"><?php echo $lang==='th'?'ไม่กรอก = สร้างอัตโนมัติ':'Leave blank = auto'; ?></p>
                </div>
                <div class="ci-fg">
                    <label><?php echo $lang==='th'?'ประเภทภาชนะ':'Type'; ?></label>
                    <select id="containerType" class="ci-select">
                        <option value="bottle"><?php echo $lang==='th'?'ขวด':'Bottle'; ?></option>
                        <option value="vial">Vial</option>
                        <option value="flask"><?php echo $lang==='th'?'ขวดรูปชมพู่':'Flask'; ?></option>
                        <option value="canister"><?php echo $lang==='th'?'ถัง':'Canister'; ?></option>
                        <option value="cylinder"><?php echo $lang==='th'?'ถังแก๊ส':'Cylinder'; ?></option>
                        <option value="ampoule"><?php echo $lang==='th'?'หลอดแก้ว':'Ampoule'; ?></option>
                        <option value="bag"><?php echo $lang==='th'?'ถุง':'Bag'; ?></option>
                        <option value="other"><?php echo $lang==='th'?'อื่นๆ':'Other'; ?></option>
                    </select>
                </div>
                <div class="ci-fg">
                    <label><?php echo $lang==='th'?'วัสดุภาชนะ':'Material'; ?></label>
                    <select id="containerMaterial" class="ci-select">
                        <option value="glass"><?php echo $lang==='th'?'แก้ว':'Glass'; ?></option>
                        <option value="plastic"><?php echo $lang==='th'?'พลาสติก':'Plastic'; ?></option>
                        <option value="metal"><?php echo $lang==='th'?'โลหะ':'Metal'; ?></option>
                    </select>
                </div>
            </div>
            <div class="form-row form-row-3">
                <div class="ci-fg">
                    <label class="req"><?php echo $lang==='th'?'ขนาดบรรจุ':'Pack Size'; ?></label>
                    <input type="number" id="packSize" class="ci-input" step="0.01" min="0.01" required placeholder="<?php echo $lang==='th'?'เช่น 25, 500':'e.g. 25, 500'; ?>">
                </div>
                <div class="ci-fg">
                    <label class="req"><?php echo $lang==='th'?'หน่วย':'Unit'; ?></label>
                    <select id="unitSelect" class="ci-select" required>
                        <optgroup label="<?php echo $lang==='th'?'ปริมาตร':'Volume'; ?>">
                            <option value="L"><?php echo $lang==='th'?'ลิตร (L)':'Liter (L)'; ?></option>
                            <option value="mL" selected><?php echo $lang==='th'?'มิลลิลิตร (mL)':'Milliliter (mL)'; ?></option>
                            <option value="µL"><?php echo $lang==='th'?'ไมโครลิตร (µL)':'Microliter (µL)'; ?></option>
                        </optgroup>
                        <optgroup label="<?php echo $lang==='th'?'น้ำหนัก':'Mass'; ?>">
                            <option value="kg"><?php echo $lang==='th'?'กิโลกรัม (kg)':'Kilogram (kg)'; ?></option>
                            <option value="g"><?php echo $lang==='th'?'กรัม (g)':'Gram (g)'; ?></option>
                            <option value="mg"><?php echo $lang==='th'?'มิลลิกรัม (mg)':'Milligram (mg)'; ?></option>
                            <option value="µg"><?php echo $lang==='th'?'ไมโครกรัม (µg)':'Microgram (µg)'; ?></option>
                        </optgroup>
                        <optgroup label="<?php echo $lang==='th'?'จำนวน':'Count'; ?>">
                            <option value="Units">Units</option>
                            <option value="pcs"><?php echo $lang==='th'?'ชิ้น':'Pieces'; ?></option>
                            <option value="box"><?php echo $lang==='th'?'กล่อง':'Box'; ?></option>
                            <option value="kit"><?php echo $lang==='th'?'ชุด':'Kit/Set'; ?></option>
                        </optgroup>
                    </select>
                </div>
                <div class="ci-fg">
                    <label><?php echo $lang==='th'?'ปริมาณคงเหลือ':'Remaining Qty'; ?></label>
                    <input type="number" id="remainingQty" class="ci-input" step="0.01" min="0" placeholder="<?php echo $lang==='th'?'= ขนาดบรรจุ':'= pack size'; ?>">
                    <p class="ci-hint"><?php echo $lang==='th'?'ว่าง = เท่ากับขนาดบรรจุ':'Blank = same as pack size'; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 3: Storage Location -->
    <div class="ci-card">
        <div class="ci-card-head"><span><i class="fas fa-map-marker-alt" style="color:#c62828"></i> <?php echo $lang==='th'?'สถานที่จัดเก็บ':'Storage Location'; ?></span></div>
        <div class="ci-card-body">
            <div class="form-row form-row-3">
                <div class="ci-fg">
                    <label><?php echo $lang==='th'?'อาคาร':'Building'; ?></label>
                    <select id="buildingSelect" class="ci-select"><option value="">— <?php echo $lang==='th'?'เลือกอาคาร':'Select'; ?> —</option></select>
                </div>
                <div class="ci-fg">
                    <label><?php echo $lang==='th'?'ห้อง':'Room'; ?></label>
                    <select id="roomSelect" class="ci-select" disabled><option value="">— <?php echo $lang==='th'?'เลือกอาคารก่อน':'Select building'; ?> —</option></select>
                </div>
                <div class="ci-fg">
                    <label><?php echo $lang==='th'?'ตู้เก็บ':'Cabinet'; ?></label>
                    <select id="cabinetSelect" class="ci-select" disabled><option value="">— <?php echo $lang==='th'?'เลือกห้องก่อน':'Select room'; ?> —</option></select>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 4: Purchase Info -->
    <div class="ci-card">
        <div class="ci-card-head"><span><i class="fas fa-receipt" style="color:#1565c0"></i> <?php echo $lang==='th'?'ข้อมูลการจัดซื้อ':'Purchase Info'; ?></span></div>
        <div class="ci-card-body">
            <div class="form-row form-row-3">
                <div class="ci-fg">
                    <label>Invoice No.</label>
                    <input type="text" id="invoiceNo" class="ci-input" placeholder="<?php echo $lang==='th'?'เลขใบเสร็จ':'Invoice number'; ?>">
                </div>
                <div class="ci-fg">
                    <label><?php echo $lang==='th'?'ราคา (บาท)':'Price (THB)'; ?></label>
                    <input type="number" id="price" class="ci-input" step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="ci-fg">
                    <label><?php echo $lang==='th'?'แหล่งทุน':'Funding Source'; ?></label>
                    <select id="fundingSelect" class="ci-select"><option value="">—</option></select>
                </div>
            </div>
            <div class="form-row form-row-2">
                <div class="ci-fg">
                    <label><?php echo $lang==='th'?'โครงการ':'Project'; ?></label>
                    <input type="text" id="projectName" class="ci-input" placeholder="<?php echo $lang==='th'?'ชื่อโครงการ (ถ้ามี)':'Project name'; ?>">
                </div>
                <div class="ci-fg">
                    <label><?php echo $lang==='th'?'หมายเหตุ':'Notes'; ?></label>
                    <input type="text" id="notes" class="ci-input" placeholder="<?php echo $lang==='th'?'หมายเหตุเพิ่มเติม':'Additional notes'; ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Preview -->
    <div class="form-preview" id="formPreview" style="display:none">
        <h3><i class="fas fa-eye"></i> <?php echo $lang==='th'?'ตรวจสอบก่อนบันทึก':'Review Before Saving'; ?></h3>
        <div class="preview-grid" id="previewGrid"></div>
    </div>

    <div class="submit-bar">
        <a href="/v1/pages/containers.php" class="ci-btn ci-btn-secondary"><i class="fas fa-arrow-left"></i> <?php echo $lang==='th'?'ยกเลิก':'Cancel'; ?></a>
        <button type="button" onclick="previewForm()" class="ci-btn ci-btn-outline"><i class="fas fa-eye"></i> <?php echo $lang==='th'?'ตรวจสอบ':'Preview'; ?></button>
        <button type="submit" class="ci-btn ci-btn-primary" id="submitBtn"><i class="fas fa-save"></i> <?php echo $lang==='th'?'บันทึกเข้าคลัง':'Save to Inventory'; ?></button>
    </div>
</form>
</div>

<?php else: ?>
<!-- ═══ LIST VIEW ═══ -->
<?php Layout::pageHeader(
    __('containers_title'), 
    'fas fa-box', 
    '',
    '<a href="/v1/pages/containers.php?action=add" class="ci-btn ci-btn-primary"><i class="fas fa-plus-circle"></i> '.($lang==='th'?'เพิ่มขวดสาร':'Add Bottle').'</a>'
); ?>

<div class="ci-card" style="margin-bottom:16px">
    <div class="ci-card-body ci-filter-bar">
        <div style="flex:1;min-width:200px;position:relative">
            <i class="fas fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#bbb;font-size:13px"></i>
            <input type="text" id="searchInput" class="ci-input" style="padding-left:32px" placeholder="<?php echo $lang==='th'?'ค้นหาด้วย รหัสขวด, QR, ชื่อสารเคมี...':'Search by bottle code, QR, chemical name...'; ?>">
        </div>
        <select id="statusFilter" class="ci-select" style="width:160px">
            <option value=""><?php echo __('all'); ?></option>
            <option value="active"><?php echo __('status_active'); ?></option>
            <option value="empty"><?php echo __('status_empty'); ?></option>
            <option value="expired"><?php echo __('status_expired'); ?></option>
            <option value="quarantined"><?php echo __('status_quarantined'); ?></option>
        </select>
    </div>
</div>

<div id="containersList" class="ci-auto-grid">
    <div class="ci-card ci-card-body" style="height:180px;background:#f9f9f9"></div>
    <div class="ci-card ci-card-body" style="height:180px;background:#f9f9f9"></div>
    <div class="ci-card ci-card-body" style="height:180px;background:#f9f9f9"></div>
</div>

<div id="pagination" class="ci-pagination" style="display:none">
    <span id="showingText"></span>
    <div class="ci-pagination-btns">
        <button id="prevBtn" class="ci-btn ci-btn-secondary ci-btn-sm" disabled><i class="fas fa-chevron-left"></i></button>
        <button id="nextBtn" class="ci-btn ci-btn-secondary ci-btn-sm"><i class="fas fa-chevron-right"></i></button>
    </div>
</div>
<?php endif; ?>

<?php Layout::endContent(); ?>
<script>
const LANG='<?php echo $lang; ?>',ACTION='<?php echo $action; ?>';
const t={no_results:'<?php echo __("no_results"); ?>',qty:'<?php echo __("containers_quantity"); ?>',loc:'<?php echo __("containers_location"); ?>',owner:'<?php echo __("containers_owner"); ?>',expiry:'<?php echo __("containers_expiry"); ?>',showing:'<?php echo __("showing"); ?>',of:'<?php echo __("of"); ?>'};

<?php if ($action === 'add'): ?>
// ═══ ADD BOTTLE LOGIC ═══
(async function initAddForm(){
    const load=async(url)=>{try{const d=await apiFetch(url);return d.success?d.data:[]}catch(e){return[]}};
    
    // Load buildings
    const buildings=await load('/v1/api/locations.php?type=buildings');
    const bSel=document.getElementById('buildingSelect');
    buildings.forEach(b=>{const o=document.createElement('option');o.value=b.id;o.textContent=b.shortname?b.name+' ('+b.shortname+')':b.name;bSel.appendChild(o)});
    
    // Load funding sources
    const funding=await load('/v1/api/locations.php?type=funding');
    const fSel=document.getElementById('fundingSelect');
    funding.forEach(f=>{const o=document.createElement('option');o.value=f.id;o.textContent=f.name;fSel.appendChild(o)});
    
    // Load manufacturers autocomplete
    const mfrs=await load('/v1/api/locations.php?type=manufacturers');
    const mDl=document.getElementById('mfrSuggestions');
    mfrs.forEach(m=>{const o=document.createElement('option');o.value=m.name;mDl.appendChild(o)});
    
    // Load chemicals autocomplete
    try{
        const d=await apiFetch('/v1/api/chemicals.php?limit=200');
        if(d.success){
            const items=d.data?.data||d.data?.chemicals||d.data||[];
            const dl=document.getElementById('chemSuggestions');
            items.forEach(c=>{const o=document.createElement('option');o.value=c.name||c.chemical_name;if(c.cas_number)o.label=c.cas_number;dl.appendChild(o)});
        }
    }catch(e){}
})();

// Cascading selects
document.getElementById('buildingSelect').addEventListener('change',async function(){
    const rm=document.getElementById('roomSelect');
    rm.innerHTML='<option value="">— '+(LANG==='th'?'กำลังโหลด...':'Loading...')+' —</option>';rm.disabled=true;
    document.getElementById('cabinetSelect').innerHTML='<option value="">—</option>';document.getElementById('cabinetSelect').disabled=true;
    if(!this.value){rm.innerHTML='<option value="">— '+(LANG==='th'?'เลือกอาคารก่อน':'Select building')+' —</option>';return}
    try{
        const d=await apiFetch('/v1/api/locations.php?type=rooms&building_id='+this.value);
        if(d.success&&d.data.length){
            rm.innerHTML='<option value="">— '+(LANG==='th'?'เลือกห้อง':'Select Room')+' —</option>';
            d.data.forEach(r=>{const o=document.createElement('option');o.value=r.id;o.textContent=(r.code||r.room_number?((r.code||r.room_number)+' - '):'')+r.name;rm.appendChild(o)});
            rm.disabled=false;
        }else{rm.innerHTML='<option value="">— '+(LANG==='th'?'ไม่มีห้องในอาคารนี้':'No rooms')+' —</option>'}
    }catch(e){}
});
document.getElementById('roomSelect').addEventListener('change',async function(){
    const cb=document.getElementById('cabinetSelect');
    cb.innerHTML='<option value="">— '+(LANG==='th'?'กำลังโหลด...':'Loading...')+' —</option>';cb.disabled=true;
    if(!this.value)return;
    try{
        const d=await apiFetch('/v1/api/locations.php?type=cabinets&room_id='+this.value);
        if(d.success&&d.data.length){
            cb.innerHTML='<option value="">— '+(LANG==='th'?'เลือกตู้':'Select Cabinet')+' —</option>';
            d.data.forEach(c=>{const o=document.createElement('option');o.value=c.id;o.textContent=c.name;cb.appendChild(o)});
            cb.disabled=false;
        }else{cb.innerHTML='<option value="">— '+(LANG==='th'?'ไม่มีตู้':'No cabinets')+' —</option>'}
    }catch(e){}
});

// Update step indicator
document.querySelectorAll('.ci-card').forEach((card,i)=>{
    card.addEventListener('focusin',()=>{
        document.querySelectorAll('.step-dot').forEach((d,j)=>{d.classList.toggle('active',j===i);d.classList.toggle('done',j<i)});
    });
});

function previewForm(){
    const name=document.getElementById('chemName').value;
    if(!name){alert(LANG==='th'?'กรุณากรอกชื่อสารเคมี':'Please enter chemical name');document.getElementById('chemName').focus();return}
    const pack=document.getElementById('packSize').value;
    if(!pack){alert(LANG==='th'?'กรุณากรอกขนาดบรรจุ':'Please enter pack size');document.getElementById('packSize').focus();return}
    const bldg=document.getElementById('buildingSelect'),room=document.getElementById('roomSelect');
    const items=[
        [LANG==='th'?'ชื่อสารเคมี':'Chemical',name],
        ['CAS',document.getElementById('casNumber').value||'-'],
        [LANG==='th'?'เกรด':'Grade',document.getElementById('gradeSelect').value||'-'],
        [LANG==='th'?'ผู้ผลิต':'Manufacturer',document.getElementById('manufacturer').value||'-'],
        [LANG==='th'?'ขนาดบรรจุ':'Pack Size',pack+' '+document.getElementById('unitSelect').value],
        [LANG==='th'?'ประเภท':'Type',document.getElementById('containerType').value],
        [LANG==='th'?'อาคาร':'Building',bldg.options[bldg.selectedIndex]?.text||'-'],
        [LANG==='th'?'ห้อง':'Room',room.options[room.selectedIndex]?.text||'-'],
        [LANG==='th'?'ราคา':'Price',(document.getElementById('price').value||'0')+' ฿'],
        [LANG==='th'?'ผู้เพิ่ม':'Added By','<?php echo $displayName; ?>'],
    ];
    document.getElementById('previewGrid').innerHTML=items.map(([l,v])=>`<div class="preview-item"><span class="preview-label">${l}</span><span class="preview-value">${v}</span></div>`).join('');
    const pv=document.getElementById('formPreview');pv.style.display='block';pv.scrollIntoView({behavior:'smooth'});
}

document.getElementById('addBottleForm').addEventListener('submit',async function(e){
    e.preventDefault();
    const btn=document.getElementById('submitBtn');btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> '+(LANG==='th'?'กำลังบันทึก...':'Saving...');
    const packSize=parseFloat(document.getElementById('packSize').value);
    const remaining=document.getElementById('remainingQty').value?parseFloat(document.getElementById('remainingQty').value):packSize;
    const body={
        chemical_name:document.getElementById('chemName').value.trim(),
        cas_number:document.getElementById('casNumber').value.trim(),
        grade:document.getElementById('gradeSelect').value,
        manufacturer:document.getElementById('manufacturer').value.trim(),
        physical_state:document.getElementById('physicalState').value,
        bottle_code:document.getElementById('bottleCode').value.trim(),
        container_type:document.getElementById('containerType').value,
        container_material:document.getElementById('containerMaterial').value,
        initial_quantity:packSize,
        current_quantity:remaining,
        quantity_unit:document.getElementById('unitSelect').value,
        building_id:document.getElementById('buildingSelect').value||null,
        room_id:document.getElementById('roomSelect').value||null,
        cabinet_id:document.getElementById('cabinetSelect').value||null,
        invoice_number:document.getElementById('invoiceNo').value.trim(),
        cost:document.getElementById('price').value?parseFloat(document.getElementById('price').value):null,
        funding_source_id:document.getElementById('fundingSelect').value||null,
        project_name:document.getElementById('projectName').value.trim(),
        notes:document.getElementById('notes').value.trim(),
    };
    try{
        const d=await apiFetch('/v1/api/containers.php',{method:'POST',body:JSON.stringify(body)});
        if(d.success){
            const code=d.data?.bottle_code||d.data?.id||'';
            if(confirm((LANG==='th'?'✅ บันทึกสำเร็จ!\nรหัสขวด: '+code+'\n\nต้องการเพิ่มขวดอีกหรือไม่?':'✅ Saved! Bottle: '+code+'\n\nAdd another?'))){
                document.getElementById('addBottleForm').reset();
                document.getElementById('formPreview').style.display='none';
                document.getElementById('chemName').focus();
            }else{
                window.location.href='/v1/pages/containers.php';
            }
        }else{throw new Error(d.error||'Failed')}
    }catch(er){
        alert('❌ '+(LANG==='th'?'เกิดข้อผิดพลาด: ':'Error: ')+er.message);
        btn.disabled=false;btn.innerHTML='<i class="fas fa-save"></i> '+(LANG==='th'?'บันทึกเข้าคลัง':'Save to Inventory');
    }
});

<?php else: ?>
// ═══ LIST VIEW LOGIC ═══
let containers=[],page=1,perPage=12,total=0;

async function load(){
    try{
        const p=new URLSearchParams({page,limit:perPage});
        const s=document.getElementById('searchInput').value,st=document.getElementById('statusFilter').value;
        if(s)p.set('search',s);if(st)p.set('status',st);
        const d=await apiFetch('/v1/api/containers.php?'+p);
        if(d.success){
            const raw=d.data;
            containers=raw.data||raw.containers||raw||[];
            total=raw.pagination?.total||raw.total||containers.length;
            render();
        }
    }catch(e){console.error(e)}
}
function render(){
    const el=document.getElementById('containersList');
    if(!containers.length){el.innerHTML=`<div style="grid-column:1/-1" class="ci-empty"><i class="fas fa-box-open"></i><p>${t.no_results}</p>${ACTION===''?'<a href="/v1/pages/containers.php?action=add" class="ci-btn ci-btn-primary" style="margin-top:12px"><i class="fas fa-plus-circle"></i> '+(LANG==='th'?'เพิ่มขวดสาร':'Add Bottle')+'</a>':''}</div>`;return}
    el.innerHTML=containers.map(c=>{
        const pct=parseFloat(c.remaining_percentage)||0;
        const pctColor=pct>50?'ci-progress-green':pct>20?'ci-progress-orange':'ci-progress-red';
        const isExp=c.expiry_date&&new Date(c.expiry_date)<new Date();
        const bottleCode=c.bottle_code||c.qr_code||'';
        const mfr=c.manufacturer_name||'';
        const ownerName=c.owner_name||((c.first_name||'')+' '+(c.last_name||'')).trim();
        return`<div class="ci-card bottle-card">
            <div class="ci-card-body">
                <div style="display:flex;justify-content:space-between;margin-bottom:10px">
                    <div style="display:flex;gap:10px;align-items:center;min-width:0;overflow:hidden">
                        <div class="ci-stat-icon green" style="width:36px;height:36px;font-size:14px;flex-shrink:0"><i class="fas fa-box"></i></div>
                        <div style="min-width:0;overflow:hidden">
                            <h3 style="font-weight:600;font-size:13px;color:#333;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${c.chemical_name||''}</h3>
                            ${bottleCode?`<span class="bottle-id">${bottleCode}</span>`:''}
                        </div>
                    </div>
                    ${statusBadge(c.status||'active')}
                </div>
                <div style="font-size:12px;color:#777;display:flex;flex-direction:column;gap:6px">
                    <div style="display:flex;justify-content:space-between"><span>${t.qty}</span><span style="font-weight:500;color:#333">${c.current_quantity||0} / ${c.initial_quantity||0} ${c.quantity_unit||c.unit||''}</span></div>
                    <div class="ci-progress"><div class="ci-progress-bar ${pctColor}" style="width:${pct}%"></div></div>
                    ${mfr?`<div><span class="mfr-tag"><i class="fas fa-industry"></i> ${mfr}</span></div>`:''}
                    ${c.lab_name?`<div><i class="fas fa-map-marker-alt" style="width:14px"></i> ${c.lab_name}</div>`:''}
                    ${ownerName?`<div><i class="fas fa-user" style="width:14px"></i> ${ownerName}</div>`:''}
                    ${c.expiry_date?`<div style="${isExp?'color:var(--danger);font-weight:500':''}"><i class="fas fa-calendar" style="width:14px"></i> ${formatDate(c.expiry_date)}${isExp?' ⚠️':''}</div>`:''}
                </div>
            </div>
        </div>`}).join('');
    const pgEl=document.getElementById('pagination'),tp=Math.ceil(total/perPage);
    if(tp>1){pgEl.style.display='flex';document.getElementById('showingText').textContent=`${t.showing} ${(page-1)*perPage+1}-${Math.min(page*perPage,total)} ${t.of} ${total}`;document.getElementById('prevBtn').disabled=page<=1;document.getElementById('nextBtn').disabled=page>=tp}else{pgEl.style.display='none'}
}
let sTimer;document.getElementById('searchInput').addEventListener('input',()=>{clearTimeout(sTimer);sTimer=setTimeout(()=>{page=1;load()},300)});
document.getElementById('statusFilter').addEventListener('change',()=>{page=1;load()});
document.getElementById('prevBtn').addEventListener('click',()=>{page--;load()});
document.getElementById('nextBtn').addEventListener('click',()=>{page++;load()});
load();
<?php endif; ?>
</script>
</body></html>
