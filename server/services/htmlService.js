const {
    formatDate,
    getSellerName,
    getCustomerAddress,
    formatNumber,
    formatCurrency,
    getProductImageUrl,
} = require('../utils/helpers');

// Function to format invoice number for document header.
function formatInvoiceNumber(invoiceNo, documentType, quotation = null) {
    // Single-document billing: receipt number comes from latest payment reference when available.
    if (documentType === 'billing-receipt' && quotation && quotation.latest_payment_ref_no) {
        return quotation.latest_payment_ref_no;
    }

    if (!invoiceNo) return '-';
    return invoiceNo;
}

// Function to generate document title section based on document type
function generateDocumentTitleSection(quotation, documentType) {
    const formattedInvoiceNo = formatInvoiceNumber(quotation.invoice_no, documentType, quotation);

    // let documentLabel = '';
    // switch (documentType) {
    //     case 'tax-invoice':
    //         documentLabel = 'เลขที่ใบกำกับภาษี';
    //         break;
    //     case 'billing-receipt':
    //         documentLabel = 'เลขที่ใบเสร็จรับเงิน';
    //         break;
    //     case 'quotation':
    //         documentLabel = 'เลขที่ใบเสนอราคา';
    //         break;
    //     default:
    //         documentLabel = 'เลขที่เอกสาร';
    // }
    let documentLabel = '';
    switch (documentType) {
        case 'tax-invoice':
            documentLabel = 'เลขที่ใบกำกับภาษี';
            break;
        case 'billing-receipt':
            documentLabel = 'เลขที่ใบเสร็จรับเงิน';
            break;
        case 'quotation':
            documentLabel = 'เลขที่ใบเสนอราคา';
            break;
        default:
            documentLabel = 'เลขที่เอกสาร';
    }

    return `
        <div class="section-title thai-text section-title-bill-number">
            Date : ${formatDate(
                quotation.transaction_date
            )} ${documentLabel} : ${formattedInvoiceNo}
        </div>
    `;
}

// Function to generate header based on document type and version
function generateHeader(currentPage, totalPages, documentType, version = 'original') {
    let headerContent = '';

    switch (documentType) {
        case 'quotation':
            headerContent = `
                <div class="receipt-type-en thai-text">Quotations</div><br />
                <div class="receipt-type-th thai-text">ใบเสนอราคา</div>
            `;
            break;

        case 'tax-invoice':
            if (version === 'original') {
                headerContent = `
                    <div class="receipt-type-en-tax thai-text">TAX INCOICE/INVOICE/DELIVERY ORDER (Original)</div><br />
                    <div class="receipt-type-th thai-text">(ต้นฉบับ) ใบกำกับภาษี / ใบแจ้งหนี้ / ใบส่งสินค้า</div>
                `;
            } else {
                headerContent = `
                    <div class="receipt-type-en-tax thai-text">TAX INCOICE/INVOICE/DELIVERY ORDER(copy)</div><br />
                    <div class="receipt-type-th thai-text">(สำเนา)ใบกำกับภาษี / ใบแจ้งหนี้ / ใบส่งสินค้า</div>
                `;
            }
            break;

        case 'billing-receipt':
            if (version === 'original') {
                headerContent = `
                    <div class="receipt-type-en thai-text">Billing receipt (Original)</div><br />
                    <div class="receipt-type-th thai-text">(ต้นฉบับ) ใบเสร็จรับเงิน</div>
                `;
            } else {
                headerContent = `
                    <div class="receipt-type-en thai-text">Billing receipt (copy)</div><br />
                    <div class="receipt-type-th thai-text">(สำเนา)ใบเสร็จรับเงิน</div>
                `;
            }
            break;

        default:
            headerContent = `
                <div class="receipt-type-en thai-text">Document</div><br />
                <div class="receipt-type-th thai-text">เอกสาร</div>
            `;
    }

    return `
        <div class="header">
            ${headerContent}
            <div class="page-number thai-text">หน้าที่ ${currentPage}/${totalPages}</div>
            <div class="company-name">RUBYSHOP</div>
            <div class="company-subtitle thai-text">ห้างหุ้นส่วนจำกัดรูบี้ช๊อป</div>
        </div>
    `;
}

function getDefaultQuotationNotes() {
    return [
        'รับประกันซ่อมฟรี 1 ปี (ไม่รวมอะไหล่ )',
        'มีค่าใช้จ่ายในการ รับ - ส่ง (กรณีส่งซ่อม)',
        'Service หลังการขาย ส่งสินค้าเข้าศูนย์บริการที่ดอนเมือง',
    ].join('\n');
}

function getDefaultChequeNote() {
    return 'ใบเสร็จรับเงินจะสมบูรณ์เมื่อเช็คผ่าน หจก.รูบี้ช๊อป โดยสามารถตรวจสอบได้ หรือโทรสอบถามเพิ่มเติมได้ที่เรียงรายละเอียดข้างล่าง';
}

function getNotesByDocumentType(documentType, quotation = {}) {
    const additionalNotes =
        typeof quotation.additional_notes === 'string' ? quotation.additional_notes.trim() : '';

    if (documentType === 'quotation') {
        return additionalNotes || getDefaultQuotationNotes();
    }

    return additionalNotes || `* ${getDefaultChequeNote()}`;
}

function decodeBasicHtmlEntities(text = '') {
    return text
        .replace(/&#x2f;|&#47;|&sol;/gi, '/')
        .replace(/&lt;/gi, '<')
        .replace(/&gt;/gi, '>')
        .replace(/&quot;/gi, '"')
        .replace(/&#39;|&apos;/gi, "'")
        .replace(/&lpar;/gi, '(')
        .replace(/&rpar;/gi, ')')
        .replace(/&nbsp;/gi, ' ')
        .replace(/&amp;/gi, '&');
}

function normalizeNoteLines(notes = '') {
    const decodedNotes = decodeBasicHtmlEntities(String(notes || ''));

    const normalizedNotes = decodedNotes
        .replace(/\r\n?/g, '\n')
        .replace(/<\s*\/?\s*p\s*>/gi, '\n')
        .replace(/<\s*br\s*\/?\s*>/gi, '\n')
        .replace(/<\s*\/?\s*(ul|ol|li)\s*>/gi, '\n')
        .replace(/<[^>]+>/g, ' ')
        .replace(/\u00a0/g, ' ');

    return normalizedNotes
        .split('\n')
        .map(line => line.replace(/^\s*([*\-•]+)\s*/, '').trim())
        .filter(Boolean);
}

// Function to generate footer based on document type
function generateFooter(documentType, quotation = {}) {
    const quotationFooterNotesHtml = normalizeNoteLines(getNotesByDocumentType('quotation', quotation))
        .map(line => `<span class="english-text">${line}</span> <br />`)
        .join('');

    switch (documentType) {
        case 'quotation':
            return `
                <div class="footer">
                    <div class="payment-info-quotation">
                        <strong class="thai-text">หมายเหตุ:</strong><br />
                        ${quotationFooterNotesHtml}
                       
                    </div>
                    
                    <div class="collector-info-quotation">
                     ..................................................................................................................................
                    </div>
                </div>
            `;

        case 'tax-invoice':
            return `
                <div class="footer">
                    <div class="footer-tax-invoice">
                        <div class="footer-section-left">
                            <div class="section-title-receivedby">
                                <strong class="thai-text">ผู้รับสินค้า</strong><br>
                                <span class="english-text">Received By</span>
                            </div>
                            <div class="conditions">
                                <div class="thai-text">ได้รับสินค้าครบตามรายการพร้อม</div>
                                <div class="thai-text">ได้รับใบกำกับภาษีเรียบร้อยแล้ว</div>
                                <div class="thai-text">โปรดลงลายมือชื่อด้วยตัวบรรจง</div>
                            </div>
                            <div class="signature-line-bottom-receivedby">
                                <div class="thai-text signature-line-bottom-receivedby-line">………………………………………………….</div>
                                <div class="english-text signature-line-bottom-receivedby-text">ผู้รับสินค้า/Received By</div>
                            </div>
                            <div class="signature-line-bottom-receivedby-date">
                                <div class="thai-text signature-line-bottom-receivedby-date-line">………………………………………………….</div>
                                <div class="thai-text signature-line-bottom-receivedby-date-text">วันที่/Date</div>
                            </div>
                        </div>
                        
                        <div class="footer-section-center">
                            <div class="section-title-conditions">
                                <strong class="thai-text">เงื่อนไขข้อตกลง</strong><br>
                                <span class="english-text">Terms and Conditions</span>
                            </div>
                            <div class="terms">
                                <div class="thai-text">*ได้รับสินค้าตามรานการข้างต้นนี้ถูกต้อง</div>
                                <div class="thai-text">และยินยอมในเงื่อนไขตามเอกสารนี้</div><br />
                                <div class="thai-text">*กรุณาสั่งจ่ายเช็คขีดคร่อมในนาม</div>
                                <div class="thai-text">ห้างหุ้นส่วนจำกัดรูบี้ช๊อป</div><br />
                                <div class="thai-text">*บริษัทจะคิดอัตราดอกเบี้ย 1.5% ต่อเดือน</div>
                                <div class="thai-text">สำหรับใบแจ้งหนี้ที่ไม่ชำระตามกำหนด</div>
                            </div>
                        </div>
                        
                        <div class="footer-section-right">
                            <div class="signature-line-bottom section-title-conditions">
                                <div class="thai-text">ผู้ส่งสินค้า<br/>Delivered By</div>
                              
                                 
                            </div>
                            <div class="signature-line-bottom">
                                <div class="thai-text">…………………………………….</div>
                                 <div class="thai-text">ผู้ตรวจสอบสินค้า/QC1</div>
                            </div>
                            <div class="signature-line-bottom">
                                <div class="thai-text">.......................................</div>
                                 <div class="thai-text">ผู้ส่งสินค้า Delivered By</div>
                              
                            </div>
                            <div class="approve-section-small">
                                <div class="thai-text">ผู้ส่งสินค้า Delivered By</div>
                            </div>
                       
                            </div>
                             
                        </div>
                          <div class="collector-info-tax-invoice">
                     ..................................................................................................................................
                    </div>
                </div>
            `;

        case 'billing-receipt':
            return `
                <div class="footer">
                       
                    <div class="billing-receipt-info">
                      
                        <div class="footer-section-left">
                            <div class="section-title-receipt-info">
                          
                            </div>
                               <div class="header-footer">
                               <p class="header-footer-text"> การชำระเงิน<br /> Payment information <br /></p></div>
                            <div class="payment-method-line">
                               
                                <span class="thai-text">ชำระโดย <br />Paid By</span>
                                <span class="checkbox"></span>
                                <span class="thai-text">เงินสด <br />Cash</span>
                                <span class="checkbox"></span>
                                <span class="thai-text">เช็ค <br />Cheque</span>
                                <span class="checkbox"></span>
                                <span class="thai-text">เงินโอน <br />Transfer</span>
                            </div>
                            <div class="bank-details">
                                <div class="bank-line">
                                    <span class="thai-text">ธนาคาร<br /></span>
                                    <span class="english-text">Bank ...............</span>
                                   
                           
                                </div>
                                <div class="bank-line">
                                  <span class="thai-text">สาขา <br /></span>
                                  <span class="english-text">Branch ...............</span>
                               
                                </div>
                                <div class="bank-line">
                                     <span class="thai-text">เลขที่เช็ค<br /> </span>
                                    <span class="english-text">Cheque ..................</span>
                                </div>
                              
                            </div>
                                <div class="date-amount-line">
                                    <span class="thai-text">ลงวันที่ Date .................................</span>
                                    <span class="thai-text">จำนวนเงิน Amount ..........................</span>
                                </div>
                      </div>
                        
                        <div class="footer-section-center-bill">
                            <div class="section-title-bill-header">
                                 ผู้รับเงิน<br /> Collecrtor 
                            </div>
                            <div class="collector-section-bill">
                                <div class="collector-line-bill-header">
                                  
                                <div class="signature-line-bottom">
                             
                                </div>
                                <div class="signature-line-bottom">
                                   ............................................
                                </div>
                                <div class="signature-line-bottom">
                                         ผู้รับ/Collector 
                                </div>
                                <div class="signature-line-bottom">
                                   ............................................
                                </div>
                                <div class="signature-line-bottom">
                                         วันที่/Date 
                                </div>
                            </div>
                        </div>
                    </div>
               
                    <div class="collector-info-bill">
                     ..................................................................................................................................
                    </div>
                
                </div>
            `;

        default:
            return `
                <div class="footer">
                    <div class="payment-info">
                        <strong class="thai-text">การชำระเงิน</strong><br>
                        <span class="english-text">Payment Information</span>
                    </div>
                </div>
            `;
    }
}

function generateMainNotesSection(documentType, quotation = {}) {
    if (documentType === 'quotation') {
        return '';
    }

    const notes = getNotesByDocumentType(documentType, quotation);
    const notesHtml = normalizeNoteLines(notes).join('<br />');

    return `
            <!-- Notes Section - Left side -->
            <div class="products-services selenote">
                <h4 class="thai-text">หมายเหตุ</h4>
                <div class="thai-text">
                    ${notesHtml}
                </div>
            </div>
    `;
}

// Function to generate HTML content with pagination support for Quotations
function generateQuotationHTML(quotation, lineItems) {
    return generateDocumentHTML(quotation, lineItems, 'quotation');
}

// Function to generate HTML content with pagination support for Tax Invoice
function generateTaxInvoiceHTML(quotation, lineItems) {
    return generateDocumentHTML(quotation, lineItems, 'tax-invoice');
}

// Function to generate HTML content with pagination support for Billing Receipt
function generateBillingReceiptHTML(quotation, lineItems) {
    return generateDocumentHTML(quotation, lineItems, 'billing-receipt');
}

// Generic function to generate HTML content with pagination support
function generateDocumentHTML(quotation, lineItems, documentType) {
    // Use database values instead of recalculating
    const subtotal = parseFloat(quotation.total_before_tax) || 0;
    const discount = parseFloat(quotation.discount_amount) || 0;
    const freight = parseFloat(quotation.shipping_charges) || 0;
    const vatAmount = parseFloat(quotation.tax_amount) || 0;
    const total = parseFloat(quotation.final_total) || 0;

    // Dynamic pagination logic based on content height
    const itemsPerPage = calculateItemsPerPage(lineItems, documentType);
    const totalItems = lineItems.length;
    const totalPages = Math.max(1, Math.ceil(totalItems / itemsPerPage));

    console.log(
        `Document Type: ${documentType}, Total Items: ${totalItems}, Items Per Page: ${itemsPerPage}, Total Pages: ${totalPages}`
    );

    // Log product table height tracking information
    console.log('=== PRODUCT TABLE HEIGHT TRACKING ===');
    console.log(`Total line items: ${lineItems.length}`);
    lineItems.forEach((item, index) => {
        const descLength = (item.product_description || '').length;
        const hasImage = documentType === 'quotation';
        console.log(`  Item ${index + 1}: "${item.product_name}" | Desc length: ${descLength} chars | Has image: ${hasImage}`);
    });
    console.log(`Estimated items per page: ${itemsPerPage}`);
    console.log(`Calculated total pages: ${totalPages}`);
    console.log('=====================================');

    let allPagesHTML = '';

    // For quotations: only generate one version
    // For tax-invoice and billing-receipt: generate both original and copy versions
    const versions = documentType === 'quotation' ? ['original'] : ['original', 'copy'];

    versions.forEach((version, versionIndex) => {
        console.log(`Generating ${version} version (${versionIndex + 1}/${versions.length})`);

        for (let currentPage = 1; currentPage <= totalPages; currentPage++) {
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = Math.min(startIndex + itemsPerPage, totalItems);
            const pageItems = lineItems.slice(startIndex, endIndex);
            const isLastPage = currentPage === totalPages;

            console.log(
                `${version} version - Page ${currentPage}/${totalPages}, isLastPage: ${isLastPage}`
            );

            const pageContent = generatePageContent(
                quotation,
                pageItems,
                subtotal,
                discount,
                freight,
                vatAmount,
                total,
                currentPage,
                totalPages,
                isLastPage,
                documentType,
                version,
                startIndex
            );

            allPagesHTML += pageContent;
        }
    });

    return wrapInHTMLDocument(allPagesHTML);
}

// Function to calculate items per page - based on document type
function calculateItemsPerPage(lineItems, documentType) {
    // Quotations: 4 items per page (has product images, needs more space)
    // Tax-invoice & Billing-receipt: 8 items per page (text only, more compact)
    let itemsPerPage;
    
    if (documentType === 'quotation') {
        itemsPerPage = 4;
    } else {
        itemsPerPage = 8;
    }
    
    console.log('=== PRODUCT TABLE HEIGHT CALCULATION ===');
    console.log(`Document Type: ${documentType}`);
    console.log(`Items Per Page: ${itemsPerPage}`);
    console.log(`Total Items: ${lineItems.length}`);
    console.log(`Total Pages: ${Math.ceil(lineItems.length / itemsPerPage)}`);
    console.log('========================================');

    return itemsPerPage;
}





// Function to generate page content
function generatePageContent(
    quotation,
    lineItems,
    subtotal,
    discount,
    freight,
    vatAmount,
    total,
    currentPage,
    totalPages,
    isLastPage,
    documentType,
    version = 'original',
    startIndex = 0
) {
    return `
        <div class="page" data-page="${currentPage}">
            <!-- Header -->
            ${generateHeader(currentPage, totalPages, documentType, version)}
            
            <!-- Contact Bar -->
            <div class=" header-red">
                97/60 ม.4 • Larkland • Sq 1, Viphavadi-Rangit Road, Saikun, Dongmuang, Bangkok 10210 THAILAND
            </div>
                <div class="contact-bar ">
                TEL:(+66) 8 9 666 7802 FAX : (662) 981-1584 Email: info@rubyshop.co.th www.rubyshop.co.th 
            </div>

            <!-- Main Content -->
            <div class="main-content">
                           <div class="left-section">
                           <div class="vertical-text-client-eng">
                         
                           </div>
                             <div class="vertical-text-client-eng-text">
                               <p>CLIENT INFORMATION</p>
                           </div>
                          <div class="vertical-text-client-thai">
                                <p>ข้อมูลลูกค้า</p>
                           </div>
                            
                             
                          <div class="vertical-text-company-thai">
                                <p>ข้อมูลผู้เสนอราคา</p>
                           </div>
                         
                    ${generateDocumentTitleSection(quotation, documentType)}
                    <div class="info-row">
                        <span class="info-label-left thai-text info-label-left-name">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ชื่อบริษัท:</span>
                        <span class="info-left-label-name">${
                            quotation.supplier_business_name || quotation.customer_name || ''
                        }</span>
                       
                    </div>
                    <div class="info-row">
                        <span class="info-label-left thai-text info-label-left-texnumber">เลขประจำตัวผู้เสียภาษี:</span>
                        <span class="info-left-label-texnumber">${
                            quotation.customer_tax_number || '-'
                        }</span>
                    </div>
                 <div class="info-row">
                       <span class="info-label info-value-address">ที่อยู่:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                       <span class="info-value-address">
                  ${quotation.shipping_address || getCustomerAddress(quotation) || '-'}
                    </span>
</div>

                    <div class="info-row">
                        <span class="info-label-left thai-text info-label-left-phone">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;โทรศัพท์:</span>
                        <span class="info-left-label-phone">${
                            quotation.customer_mobile || quotation.mobile || '-'
                        }</span>
                    </div>
                </div>
               
                <div class="right-section">
                  
                       <div class="vertical-text-company-eng">
                          
                           </div>
                
                     <div class="vertical-text-company-eng-text-eng">
                               <p>COOMPANY INFORMATION</p>
                           </div>
                       <div class="vertical-text-company-text-th">
                               <p>ข้อมูลบริษัทผู้ขาย</p>
                           </div>
                           <div class="thai-text section-title-company">หจก.รูบี้ช๊อป (สำนักงานใหญ่)</div>
                    <div class="info-row">
                        <span class=" section-title-company-sub">เลขที่ 97/60 หมู่บ้านหลักสี่แลนด์ ซอยโกสุมรวมใจ39<br>
                       แขวงดอนเมือง เขตดอนเมือง กรุงเทพฯ 10210 </span>
                    </div>
                    <div class="info-row">
                        <span class=" tax-number-company">เลขประจำตัวผู้เสียภาษี:</span>
                        <span class="info-value info-value-company-detail">&nbsp;&nbsp;  0103555019171</span>
                    </div>
                    <div class="info-row">
                        <span class="phone-company-text ">เบอร์โทรศัพท์: </span>
                        <span class="info-value info-value-company-detail-phone">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;089-666-7802</span>
                    </div>
                    <div class="info-row">
                        <span class="info-value-company-detail-email-th">อีเมล: &nbsp;&nbsp;&nbsp;info@rubyshop.co.th</span>
                        <!-- <span class="info-value nfo-value-company-detail-email">&nbsp;&nbsp;&nbsp;info@rubyshop.co.th</span> -->
                    </div>
                 
                    <div class="info-row">
                        <span class="info-value-company-detail-sellers">ชื่อผู้ขาย: ${getSellerName(
                            quotation
                        )}</span>
                        <!-- <span class="info-value ">${getSellerName(quotation)}</span> -->
                    </div>
                </div>
            </div>

        
    <div class="vertical-products-services"> 
                         
                           </div>
                            <div class="vertical-products-services-eng">
                               <p>PRODUCTS AND SERVICES DESCRIPTION </p>
                           </div>
                          <div class="vertical-products-services-thai">
                                <p>สินค้าและบริการ</p>
                           </div>
                     

            <!-- Items Table -->
            <table class="items-table">
                <thead class="items-header">
                    <tr>
                        <th style="width: 5%;">ลำดับ</th>
                        <th style="width: ${documentType === 'quotation' ? '45%' : '50%'};">
                            <div class="english-text">Description of Services and Goods</div>
                        </th>
                        <th style="width: 10%;">
                            <div class="english-text">Quantity</div>
                        </th>
                        <th style="width: 15%;">
                            <div class="english-text">Price Per Unit</div>
                            <div class="thai-text">(บาท)</div>
                        </th>
                        <th style="width: 15%;">
                            <div class="english-text">Amount</div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    ${generateItemRows(lineItems, startIndex, documentType)}
                </tbody>
            </table>

          
            ${generateMainNotesSection(documentType, quotation)}
            <!-- Totals Section - Right side -->
            <div class="totals-section">
                <div class="totals-table">
                    ${generateTotalsSection(
                        subtotal,
                        discount,
                        freight,
                        vatAmount,
                        total,
                        isLastPage,
                        totalPages,
                        version,
                        currentPage
                    )}
                </div>
            </div>

            <!-- Approval Section -->
            <div class="approve-section thai-text">
                Approve By/ผู้อนุมัติรายการนี้
            </div>

            <!-- Footer -->
            ${generateFooter(documentType, quotation)}
        </div>
    `;
}

// Helper function to generate item rows with correct numbering
// documentType: 'quotation' shows images, 'tax-invoice' and 'billing-receipt' show text only
// startIndex: the starting index for numbering (0-based), passed from parent to ensure correct numbering across pages
function generateItemRows(pageItems, startIndex, documentType = 'quotation') {
    // startIndex is now passed from parent function to ensure correct numbering
    console.log(`generateItemRows: startIndex=${startIndex}, pageItems.length=${pageItems.length}`);

    // Generate rows for actual items
    let rows = pageItems
        .map((item, index) => {
            const globalIndex = startIndex + index + 1;

            // Only show product images for quotations
            const showProductImage = documentType === 'quotation';
            const productImageUrl = showProductImage ? getProductImageUrl(item) : null;

            // Create the product content with image and text layout
            let productContent = '';
            if (showProductImage && productImageUrl) {
                productContent = `
                    <div class="product-item-container">
                        <div class="product-image-container">
                            <img src="${productImageUrl}" 
                                 alt="${item.product_name}" 
                                 class="product-image" 
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                            <div class="product-image-placeholder" style="display:none;">
                                No Image
                            </div>
                        </div>
                        <div class="product-info-container">
                            <div class="product-name">${item.sku && item.sku.trim() ? `${item.sku} - ${item.product_name}` : item.product_name}</div>
                            ${
                                item.product_description && item.product_description.trim()
                                    ? `<div class="product-description">${item.product_description.trim()}</div>`
                                    : ''
                            }
                        </div>
                    </div>
                `;
            } else {
                // Text-only layout for tax-invoice, billing-receipt, or when no image available
                let productDisplay = '';
                
                // Add SKU prefix if available
                if (item.sku && item.sku.trim()) {
                    productDisplay = `${item.sku} - ${item.product_name}`;
                } else {
                    productDisplay = item.product_name;
                }
                
                if (item.product_description && item.product_description.trim()) {
                    const description = item.product_description.trim();
                    // Use existing HTML structure (ul/li tags) from the database
                    let formattedDescription = description.replace(/<li>/g, '<li>- ');
                    formattedDescription = formattedDescription.replace(/<li>- - /g, '<li>- ');
                    productDisplay += `<div class="product-description">${formattedDescription}</div>`;
                }
                productContent = `<div class="item-description">${productDisplay}</div>`;
            }

            return `
            <tr>
                <td>${globalIndex}</td>
                <td class="item-description-cell">${productContent}</td>
                <td>${formatNumber(item.quantity)}</td>
                <td>${formatCurrency(item.unit_price_inc_tax)}</td>
                <td>${formatCurrency(item.unit_price_inc_tax * item.quantity)}</td>
            </tr>
        `;
        })
        .join('');

    return rows;
}

// Helper function to generate totals section
function generateTotalsSection(
    subtotal,
    discount,
    freight,
    vatAmount,
    total,
    isLastPage,
    totalPages,
    version = 'original',
    currentPage = 1
) {
    // Determine if we should show actual values or empty values
    // Single page: always show values
    // Multiple pages: only show values on the last page, empty on all other pages
    const showValues = totalPages === 1 || (totalPages > 1 && isLastPage);

    console.log(
        `Totals Section - ${version} Version - Page ${currentPage}/${totalPages} (${
            isLastPage ? 'Last' : 'Not Last'
        }), Show Values: ${showValues}`
    );

    // Generate the summary table structure (always show the table, but values depend on page)
    let summaryRows;

    // Always show the actual values, but add overlay boxes for non-last pages
    summaryRows = [
        {
            englishLabel: 'Subtotal',
            thaiLabel: 'ยอดรวม',
            value: formatCurrency(subtotal),
            needsOverlay: !showValues,
        },
        {
            englishLabel: 'Discount',
            thaiLabel: 'ส่วนลด',
            value: formatCurrency(discount),
            needsOverlay: !showValues,
        },
        {
            englishLabel: 'Freight Cost',
            thaiLabel: 'ค่าขนส่ง',
            value: formatCurrency(freight),
            needsOverlay: !showValues,
        },
        {
            englishLabel: 'VAT 7%',
            thaiLabel: 'ภาษีมูลค่าเพิ่ม',
            value: formatCurrency(vatAmount),
            needsOverlay: !showValues,
        },
    ];

    console.log(
        `${
            showValues ? 'SHOWING REAL VALUES' : 'ADDING BLACK OVERLAYS'
        } (${version}) - Page ${currentPage} of ${totalPages}`
    );

    // Generate regular summary rows with overlay boxes when needed
    let summaryHTML = summaryRows
        .map(
            (row) => `
        <div class="total-row">
            <span class="total-label">
                <span class="english-text">${row.englishLabel}</span><br>
                <span class="thai-text">${row.thaiLabel}</span>
            </span>
            <span class="total-amount">${row.value}</span>
            ${row.needsOverlay ? '<div class="value-overlay"></div>' : ''}
        </div>
    `
        )
        .join('');

    // Generate grand total row with overlay when needed
    const grandTotalValue = `${formatCurrency(total)} <span class="thai-text">บาท</span>`;

    summaryHTML += `
        <div class="total-row grand-total">
            <span class="total-label">
                <span class="english-text">Total Price</span><br>
                <span class="thai-text">ราคารวมสุทธิ</span>
            </span>
            <span class="total-amount">${grandTotalValue}</span>
            ${!showValues ? '<div class="value-overlay"></div>' : ''}
        </div>
    `;

    return summaryHTML;
}

// Helper function to wrap content in HTML document
function wrapInHTMLDocument(content) {
    return `
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ใบเสนอราคา</title>
        <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Prompt', 'TH Sarabun New', Arial, sans-serif;
                font-size: 11px;
                line-height: 1.3;
                color: #333;
                background: white;
            }
             .info-left-label-name{
             font-size: 11px;
                          margin-left: -15px;
             }
                        .info-left-label-texnumber{
                          font-size: 11px;
                          margin-left: -15px;
                        }
                        .info-left-label-address {
                            font-size: 11px;
                            margin-left: -15px;
                        }
                        .info-left-label-phone {
                            font-size: 11px;
                            margin-left: -15px;
                        }

            .vertical-products-services{
             width:646px;
            height:50px;
            padding-left:-50px; 
            margin-right:-500px;
            margin-left:-307px;
            transform: rotate(-90deg);
            background-color:#797b7d;
              
                top:298px;
                position: relative;
                border:1px solid rgb(255, 255, 255);

            }
              .vertical-products-services-eng{
                   
                    transform: rotate(-90deg);
                    position: relative;
                       width:350px;
                      height:20px;
                    background-color:transparent;
                    padding-left:100px; 
                    left:-152px;
                    top:200px;
                          color:#ffffff;
              }

.vertical-products-services-thai {
                    color:#fff;
                    transform: rotate(-90deg);
                    position: relative;
                    left:100px;
                     background-color:transparent;
                     left:-140px;
                          width:350px;
                      height:20px;
                    top:0px
              }


            .vertical-text-client-eng-text{
                         background-color:transparent;
                          
            width:180px;
            height:40px;
            position:relative;
            transform: rotate(-90deg);
            color:#fff;
            font-size: 14px;
            font-weight: 100;
            text-align: center;
            /* display: flex; */
            align-items: center;
            justify-content: center;
            font-family: 'Prompt', 'TH Sarabun New', Arial, sans-serif;

            margin-left:-78px; 
            top:32px;
            z-index: 999 !important;
            }


          .vertical-text-client-eng {
          
                background-color:#797b7d;
            width:212px;
            height: 40px;
            position:relative;
            transform: rotate(-90deg);
            color:#fff;
            font-size: 14px;
            font-weight: 100;
            text-align: center;
            /* display: flex; */
            align-items: center;
            justify-content: center;
            font-family: 'Prompt', 'TH Sarabun New', Arial, sans-serif;
            font-size: 14px;
            margin-left:-106px; 
            top:72px;
           
            
          }
          .vertical-text-client-thai {
               background-color:transparent;
                          
            width:180px;
            height:40px;
            position:relative;
            transform: rotate(-90deg);
            color:#fff;
            font-size: 12px;
            font-weight: 100;
            text-align: center;
            /* display: flex; */
            align-items: center;
            justify-content: center;
            font-family: 'Prompt', 'TH Sarabun New', Arial, sans-serif;
            font-size: 12px;
            margin-left:-65px; 
            top:-15px;
          }

       

           
          .vertical-text-company-eng {
             background-color:#797b7d;
            width:200px;
            height:45px;
            position:relative;
            transform: rotate(-90deg);
            color:#fff;
            font-size: 14px;
            font-weight: 100;
            text-align: center;
            margin-left:-86px;
            align-items: center;
            justify-content: center;
            font-family: 'Prompt',  Arial, sans-serif;
            letter-spacing: 5px;
            top: 62px;
                
          }

        
         .vertical-text-company-eng-text-eng{
            font-weight: 100;         
            transform: rotate(-90deg);   
            width:180px;
            right:-20px;
            color:#fff;
            font-size: 12px;
             margin-left:-75px;
             margin-top:10px;
         }


         .vertical-text-company-text-th{
            font-weight: 100;         
            transform: rotate(-90deg);   
            width:180px;
            right:-20px;
            color:#fff;
            font-size: 12px;
             margin-left:-62px;
             margin-top:-50px;
         }



         .vertical-text-company-thai {
                        background-color:transparent;
                   
            width:180px;
            height:40px;
            position:absolute;
            transform: rotate(-90deg);
            color:#fff;
            font-size: 12px;
            font-weight: 100;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Prompt', 'TH Sarabun New', Arial, sans-serif;
            font-size: 12px;
             left:-93px; 
            top:10px;
          }





            .page {
                width: 100%;
                min-height: 100vh;
                padding: 0;
                margin: 0;
                page-break-inside: avoid;
            }
            
            .page:not(:last-child) {
                page-break-after: always;
            }
            
            /* Header Section - Red like in image */
            .header {
                /* background: #dc3545; */
                color: white;
                padding: 15px 20px;
                position: relative;
            
               
                padding-top:-60px;
            }
            
          .company-name {
    font-size: 32px;
    font-weight: 900;
    margin-top:0px;
    font-family: 'Prompt', sans-serif;
    margin-bottom: -2px;
    letter-spacing: 2px;
    color: red;
    text-shadow: 0 0 1px red; /* ทำให้ดูหนาขึ้น */
}

            .company-subtitle {
                font-size: 14px;
                font-family: 'Prompt', sans-serif;
                font-weight: 500;
                margin-bottom: 8px;
                color:#171616;
               
            }
            .receipt-type-en-tax{
              position: absolute;
                top: 14px;
                right: 20px;
                font-size: 12px;
                font-weight: 300;
                color:red;
                 margin-top: 10px;
                font-family: 'Prompt', sans-serif;
            
            }
            .receipt-type-en {
                position: absolute;
                top: 10px;
                right: 20px;
                font-size: 24px;
                font-weight: 500;
                color:#171616;
                 margin-top: 10px;
                font-family: 'Prompt', sans-serif;
            }
            .receipt-type-th {
                position: absolute;
                top: 25px;
                right: 30px;
                font-size: 18px;
                font-weight: 500;
                color:#171616;
                 margin-top: 25px;
                font-family: 'Prompt', sans-serif;
            }
            
            
            .page-number {
                position: absolute;
                top: 85px;
                right: 20px;
                font-size: 10px;
                color:#171616;
                font-weight: 400;
                margin-bottom: 0;
                padding-bottom: 100px;
                font-family: 'Prompt', sans-serif;
            }
            
            /* Contact Bar - Gray like in image */
            .contact-bar {
                background: #6c757d;
                color: white;
                padding: 0px 20px;
                font-size: 13px;
                padding-top:12px;
                text-align: center;
                font-family: 'Prompt', sans-serif;
                padding-bottom: 6px;
                z-index: 999 !important;
            }
               .header-red{
                margin-top:-10px;
                color: white;
                padding: 0px 20px;
                  padding-top:12px;
                  padding-bottom: 6px;
                font-size: 13px;
                text-align: center;
                font-family: 'Prompt', sans-serif;
                z-index: 999 !important;
            background: #ff050d;
          }
            /* Main Content Section */
            .main-content {
                display: flex;
                margin: 15px 20px;
                gap: 15px;
            }
            
            .left-section {
             
              width:62%;
          
              height: 170px;
              margin-right:-6px;
           
                
            }
            .right-section  {
                      flex: 1;
            padding-left:-10px;
         
            }
            .section-title {
                font-weight: 600;
                margin-bottom: 8px;
                font-size: 12px;
                font-family: 'Prompt', sans-serif;
                color: #171616;
                /* border-bottom: 1px solid #ddd; */
                padding-bottom: 4px;
                 /* background: #dbe3ea; */
                margin-top: -135px;
                padding-top:10px;
                padding-left: 160px;
                  
            }

            .section-title-conditions {
                font-weight: 600;
                margin-bottom: 5px;
                text-align: center;
                font-size: 10px;
                color: #333;
            }

            .section-title-bill-number{

                    font-weight: 600;
                margin-bottom: 8px;
                font-size: 14px;
                font-family: 'Prompt', sans-serif;
                color: #000000;
                /* border-bottom: 1px solid #ddd; */
                padding-bottom: 4px;
                 background: #dbe3ea;
                margin-top: -134px;
                padding-top:10px;
                padding:10;
                padding-left: 50px;
            }

            .section-title-company{
                             font-weight: 300;
                margin-bottom: 8px;
                font-size: 18px;
                font-family: 'Prompt', sans-serif;
                color: #333;
                /* border-bottom: 1px solid #ddd; */
                padding-bottom: 4px;
               /* background: #dbe3ea; */
                    margin-top: -50px;
                padding-top:10px;
                padding-left: 20px;
                          margin-left: 40px;

            }
            .section-title-company-sub {
                  font-size: 10px;
                  
                  padding-left: 60px;
                  margin-left: -10px;
                  font-weight:300;
                     
            }
            .phone-company-text{
                   font-size: 10px;
                  
                  padding-left: 60px;
                  margin-left: -10px;
                  font-weight:300;
            }
            .tax-number-company{
                      font-size: 10px;
                  
                  padding-left: 60px;
                  margin-left: -10px;
                  font-weight:300;
            }
            .info-row {
                display: flex;
                margin-bottom: 3px;
                font-size: 10px;
                align-items: flex-start;
                padding-left: 0px; 
               
            }
            
            .info-label {
                min-width: 80px;
                font-weight: 500;
                font-family: 'Prompt', sans-serif;
                color: #555;
              
              
            }
            .info-label-left{
                        min-width: 90px;
                font-weight: 500;
                font-family: 'Prompt', sans-serif;
                color: #555;
                /* margin-right: -52px; */

            }

            .info-label-left-texnumber {
                    margin-right: -1px;
            }
            .info-value {
                flex: 1;
                font-family: 'Prompt', sans-serif;
                color: #333;
                margin-left: -10px;
                font-size: 14px;
                font-weight: 300;
            }

            .info-value-company-detail{
                  font-family: 'Prompt', sans-serif;
                color: #333;
                   font-size: 10px;
                 font-weight: 300;

            }
             .info-label-left-phone,.info-label-left-name{
                 margin-right: 20px;
                 font-size: 12px;
                 font-weight: 300;
                 margin-left: 40px;
            }
            .info-label-left-address {
              
                   margin-left: 40px;
                 font-size: 12px;
                 font-weight: 300;
            }
            .info-label-left-texnumber{
                        font-size: 12px;
                   margin-right: 20px;
                    margin-left: 62px;
                    font-weight: 300;
            }
            .taxt-number-company {
                margin-right: 0px;
                 font-size: 12px;
                 font-weight: 300;
              
            }
            .info-value-company-detail-email-th {
                 margin-left: 50px;
                     font-size: 10px;
                 font-weight: 300;

            }
            .info-value-company-detail-sellers{
                  margin-left: 50px;
                     font-size: 10px;
                 font-weight: 300;
            }

            .info-value-company-detail-phone{
                  margin-left: -18px;
                     font-size: 10px;
                 font-weight: 300;
            }
            .info-value-company-detail-email {
                margin-left: 52px;
                   font-size: 10px;
                 font-weight: 300;
            }
            .info-value-company-detail-seller{
margin-left: -42px;
   font-size: 10px;
                 font-weight: 300;
            }


            .info-value-address {
                display: table-row;
           
                 margin-left: 146px;
                 margin-right: -320px;
                 width:200px;
            }

            .info-label, .info-value-address {
    display: table-cell;
    padding: 2px 5px;
    vertical-align: top;
}
            /* Table Section - Pink headers like in image */
            .items-table {
                width: calc(100% - 40px);
                /* margin: 15px 20px; */
                border-collapse: collapse;
                border: 1px solid #ddd;
                margin-top: -90px;
                margin-left: 41px;
            }
            
            .items-header {
                background: #dc8285;
                color: white;
            }
            
            .items-header th {
                
                z-index:999;
                text-align: center;
                font-weight: 900;
                font-size: 11px;
                font-family: 'Prompt', sans-serif;
                border-right: 1px solid rgba(255,255,255,0.3);
            }
            
            .items-header th:last-child {
                border-right: none;
            }
            
            .items-table td {
                padding: 8px;
                text-align: center;
                vertical-align: top;
                border-bottom: 1px solid #ddd;
                border-right: 1px solid #ddd;
                font-size: 12px;
                font-weight: 300;
                font-family: 'Prompt', sans-serif;
                color: #333;
            }
            
            .items-table td:last-child {
                border-right: none;
            }
            
            .item-description {
                text-align: left !important;
                font-family: 'Prompt', sans-serif;
            }
            
            .item-description * {
                list-style: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            /* Product image and layout styles */
            .product-item-container {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                padding: 5px 0;
            }
            
            .product-image-container {
                flex: 0 0 60px;
                width: 60px;
                height: 60px;
                border: 1px solid #ddd;
                border-radius: 4px;
                overflow: hidden;
                background-color: #f9f9f9;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .product-image {
                max-width: 100%;
                max-height: 100%;
                width: auto;
                height: auto;
                object-fit: contain;
            }
            
            .product-image:error,
            .product-image-placeholder {
                background-color: #f0f0f0;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 10px;
                color: #999;
                text-align: center;
                width: 100%;
                height: 100%;
            }
            
            .product-info-container {
                flex: 1;
                display: flex;
                flex-direction: column;
                gap: 3px;
            }
            
            .product-name {
                font-weight: bold;
                font-size: 12px;
                line-height: 1.2;
                color: #333;
            }
            
            .product-description {
                font-size: 11px;
                color: #666;
                font-style: italic;
                font-family: 'Prompt', sans-serif;
                line-height: 1.4;
                margin-top: 3px;
                margin-bottom: 2px;
                display: block;
            }
            
            .item-description-cell {
                text-align: left !important;
                vertical-align: top !important;
                padding: 8px !important;
            }
            
            .item-description-cell .item-description {
                padding: 0;
                margin: 0;
            }
            
            .product-description ul {
                margin: 0;
                padding: 0;
                list-style: none;
            }
            
            .product-description li {
                margin: 2px 0;
                padding: 0;
                list-style: none;
            }
            
            /* Notes Section - Left side horizontal  350px. , 160*/
            .products-services {
                position: fixed;
                bottom: 110px;
                left: 40px;
                width: 436px;
                height: 260px;
                padding: 12px;
                background-color: transparent;
                //  background: #f8f9fa;
                // border: 1px solid #e9ecef;
                // z-index: 1000;
            }
            
            .products-services h4 {
                margin-bottom: 8px;
                color: #333;
                font-size: 12px;
                font-weight: 600;
                font-family: 'Prompt', sans-serif;
            }
            
            .products-services p {
                font-size: 10px;
                line-height: 1.4;
                font-family: 'Prompt', sans-serif;
                color: #666;
                margin: 0;
            }
            .selenote {
             margin-top:100px;
             padding-top:100px;
             margin-left:20px;
                 }

            /* Totals Section - Right side horizontal  defualt 160px*/
            .totals-section {
                position: fixed;
                bottom: 160px;
                right: 20px;
                width: 300px;
                z-index: 1000;
               
                 background-color: transparent;
            }
            
            .totals-table {
                width: 100%;
                background: white;
                border: 1px solid #ddd;
            }
            
            .total-row {
                display: flex;
                justify-content: space-between;
                padding: 6px 12px;
                border-bottom: 1px solid #eee;
                font-size: 10px;
            }
            
            .total-label {
                font-family: 'Prompt', sans-serif;
                color: #555;
                text-align: left;
            }
            
            .total-amount {
                font-family: 'Prompt', sans-serif;
                color: #333;
                font-weight: 500;
                text-align: right;
            }
            
            .grand-total {
                background: #f8f9fa;
                font-weight: 700;
                font-size: 14px;
                border-top: 2px solid #dc3545;
                color: #333;
            }
            
            .grand-total .total-amount {
                font-size: 18px;
                font-weight: 700;
            }
            
            /* Value overlay boxes for hiding values on non-last pages */
            .value-overlay {
                position: absolute !important;
                background-color: black !important;
                z-index: 100 !important;
                right: 12px !important;
                top: 6px !important;
                bottom: 6px !important;
                width: 120px !important;
            }
            
            .grand-total .value-overlay {
                width: 150px !important;
            }
            
            .total-row {
                position: relative !important;
            }
            
            /* Approval Section - Red button like in image */
            .approve-section {
                position: fixed;
                bottom: 110px;
                /* left: 20px; */
                right: 20px;
                display: flex;
                justify-content: center;
                text-align: center;
                background: #dc3545;
                color: white;
                width: 38%;
                padding: 15px;
                font-weight: 600;
                font-family: 'Prompt', sans-serif !important;
                font-size: 14px;
                z-index: 999;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                /* border-radius: 4px; */
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
                text-rendering: optimizeLegibility;
            }
            
            /* Footer Section */
            .footer {
                position: fixed;
                bottom: 0px;
                left: 45px;
                right: 20px;
                display: flex;
                justify-content: space-between;
                font-size: 10px;
                
                padding-top: 10px;
                padding-bottom: 10px;
                background-color: white;
                z-index: 998;
            }
            
            .payment-info, .collector-info {
                flex: 1;
                font-family: 'Prompt', sans-serif;
            }
            
            .payment-info-quotation{
              position: fixed;
              left: 60px;
              bottom: 120px;
              width: 430px;
              margin-bottom: 0;
              z-index: 999;
              line-height: 1.35;
            }

            .collector-info {
                text-align: right;
            }
            .collector-info-quotation {
            margin-right:30px;
            margin-bottom:-10px;
            margin-top:20px;
            }
            .collector-info-tax-invoice {
              
                margin-left:50px;
            margin-bottom:-10px;
            margin-top:60px;
            }
            .collector-info-bill{
               margin-right:30px;
            margin-bottom:-40px;
            margin-top:90px;
            }
            .checkbox-group {
                display: flex;
                gap: 15px;
                margin-top: 8px;
                flex-wrap: wrap;
            }
            
            .checkbox-item {
                display: flex;
                align-items: center;
                gap: 5px;
                font-size: 10px;
            }
            
            .checkbox {
                width: 12px;
                height: 12px;
                border: 1px solid #333;
                display: inline-block;
            }
            
            /* Footer Layout for Tax Invoice */
            .footer-tax-invoice {
                display: flex;
                justify-content: space-between;
                gap: 20px;
                font-size: 9px;
                line-height: 1.3;
            }
            
            .footer-section-left {
                flex: 1;
                padding-right: 10px;
            }
            
            .footer-section-center {
                flex: 1;
                margin-left: -60px;
                margin-right: -20px;
                text-align: center;
                width:220px;
               
               
            }
                .section-title-bill-header
                {
                 padding-left:90px;
                 padding-top:20px;
                 padding-bottom:5px;
                  z-index:999;
                    background-color:#e0e3e8;
                }
            .footer-section-center-bill{
                   flex: 1;
                margin-left: -160px;
                margin-right: -25px;
                padding-bottom:0px;
                margin-bottom:0px;
                width:220px;
                color:#000;
                     
              
                z-index:999;
            
            }
                .collector-line-bill {
            
                      padding-left:-60px;
                  
                      margin-bottom:-20px;

                z-index:999;
                }

            .section-title-bill{
             
            }
            .footer-section-right {
                flex: 1;
                margin-top:-10px;
                padding-left: 8px;
                text-align: center;
            }
                .footer-approve-line {
                right:10px;
                margin-right: 10px;

                }
                 .approve-line {
                right:10px;
                margin-right: 10px;
                
                }
            
            
            .footer-tax-invoice .section-title {
                font-weight: 300;
                margin-bottom: 5px;
                text-align: center;
                font-size: 10px;
            }
            
            .section-title-receivedby {
                font-weight: 600;
                margin-bottom: 5px;
                text-align: center;
                font-size: 10px;
                color: #333;
                margin-left:-40px;
                
            }
            
            .section-title-conditions {
                font-weight: 600;
                margin-bottom: 5px;
                text-align: center;
                font-size: 10px;
                color: #333;
            }
            
            .conditions, .terms {
                font-size: 8px;
                line-height: 1.2;
                margin-bottom: 10px;
            }
            
            .signature-line-bottom {
                margin: 8px 0;
                text-align: center;
            }
            .signature-line-bottom-receivedby {
              margin-left:10px;
            }
              .signature-line-bottom-receivedby-line {
              margin-left:0px;
              }
              .signature-line-bottom-receivedby-text {
                margin-left:5px;
              }

              .signature-line-bottom-receivedby-date {
              margin-left:15px;
              }
            .signature-line-bottom-receivedby-date-line {
              margin-left:-15px;
            }
            .signature-line-bottom-receivedby-date-text {
                margin-left:12px;
            }
            
            .approve-section-small {
                font-size: 9px;
                margin: 5px 0;
                text-align: center;
                font-family: 'Prompt', sans-serif !important;
                font-weight: 500 !important;
                color: #333 !important;
                background: transparent !important;
                z-index: 1001 !important;
            }
            
            /* Footer Layout for Billing Receipt */
            .billing-receipt-info {
                display: flex;
                justify-content: space-between;
                gap: 20px;
                font-size: 9px;
                line-height: 1.3;
                margin-bottom:-8px;
                bottom:0px;
                margin-left:-45px;
                padding-left:40px;
                border:1px solid #fff;
               
                
            }
            .header-footer{
             width:440px;
            height:48px;
                 padding-left: 40px;
                margin-left:-40px;
              background-color:#e0e3e8;
            }
             .header-footer-text {
              padding-top: 20px;
              color:#000;
             }
            
            
            
              
             .collector-line-bill-header {
             
               padding-bottom:20px;
              
                
              }
            .footer-billing-receipt .section-title {
                font-weight: bold;
              
                text-align: left;
            }
            .section-title-receipt-info {
             
            }
            
            .payment-method-line {
                display: flex;
                align-items: center;
                gap: 4px;
                margin: 5px 0;
                flex-wrap: wrap;
            }
            
            .payment-method-line .checkbox {
                width: 20px;
                height: 20px;
                border: 1px solid #333;
                display: inline-block;
                margin: 0 3px;
            }
            
            .bank-details {
                margin-right: -100px;
               padding-right: -100px;
            
               display:flex;
               margin-bottom:20px;
            }
            
            .bank-line {
                width:120px;
                font-size: 8px;
                line-height: 1.2;
               
            }
            .date-amount-line{
              
                  width:290px;
                  margin-top:-20px;
                 }
            
            .collector-section {
                text-align: center;
            }
            .collector-section-bill{
          

            }
            .collector-line {
                display: flex;
                justify-content: space-between;
                margin: 5px 0;
                font-size: 8px;
            }
            
            /* Legacy styles for compatibility */
            .footer-left {
                flex: 1;
                padding-right: 20px;
                position:fixed;
                bottom: 0px;
            }
            
            .footer-center {
                flex: 1;
                padding: 0 10px;
            }
            
            .footer-right {
                flex: 0 0 150px;
                text-align: center;
            }
            
            .bank-info {
                font-size: 9px;
                line-height: 1.2;
                margin-bottom: 10px;
            }
            
            .bank-info .account-number {
                font-weight: bold;
                font-size: 10px;
            }
            
            .payment-methods {
                font-size: 9px;
            }
            
            .signature-section {
                display: flex;
                justify-content: space-between;
                margin-top: 15px;
                gap: 20px;
            }
            
            .signature-item {
                flex: 1;
                text-align: center;
            }
            
            .signature-label {
                font-size: 8px;
                margin-bottom: 5px;
                line-height: 1.1;
            }
            
            .signature-line {
                border-bottom: 1px solid #333;
                height: 30px;
                margin-top: 10px;
            }
            
            .collector-signature {
                border-bottom: 1px solid #333;
                height: 40px;
                margin-top: 20px;
                width: 120px;
                margin-left: auto;
                margin-right: auto;
            }
            
            /* Thai text specific styling */
            .thai-text {
                font-family: 'Prompt', 'TH Sarabun New', sans-serif !important;
            }
            
            .english-text {
                font-family: Arial, sans-serif;
            }
            
            @media print {
                .page {
                    page-break-after: always;
                }
                .page:last-child {
                    page-break-after: avoid;
                }
                body {
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
            }
        </style>
    </head>
    <body>
        ${content}
    </body>
    </html>
    `;
}

module.exports = {
    generateQuotationHTML,
    generateTaxInvoiceHTML,
    generateBillingReceiptHTML,
    generateDocumentHTML,
    generatePageContent,
    generateItemRows,
    generateTotalsSection,
    generateHeader,
    generateFooter,
    generateDocumentTitleSection,
    formatInvoiceNumber,
    wrapInHTMLDocument,
    calculateItemsPerPage,
};
