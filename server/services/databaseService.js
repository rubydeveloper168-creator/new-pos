const mysql = require('mysql2/promise');

class DatabaseService {
    // Get database configuration from app locals or use defaults
    static getDbConfig(req = null) {
        if (req && req.app && req.app.locals && req.app.locals.dbConfig) {
            return req.app.locals.dbConfig;
        }
        
        // Fallback configuration for direct calls
        const isProduction = process.env.NODE_ENV === 'production';
        return {
            host: process.env.DB_HOST || 'localhost',
            port: process.env.DB_PORT || 3306,
            user: process.env.DB_USER || 'root',
            password: process.env.DB_PASSWORD || 'root258369',
            database: process.env.DB_NAME || 'shop_rubyshop_pos',
        };
    }

    static async getQuotationData(quotationId, req = null) {
        const dbConfig = this.getDbConfig(req);
        const connection = await mysql.createConnection(dbConfig);

        try {
            // Get quotation data
            const [quotationRows] = await connection.execute(
                `
                SELECT 
                    t.*,
                    c.name as customer_name,
                    c.mobile as customer_mobile,
                    c.email as customer_email,
                    c.address_line_1,
                    c.address_line_2,
                    c.city,
                    c.state,
                    c.country,
                    c.zip_code,
                    c.supplier_business_name,
                    c.contact_id as customer_contact_id,
                    c.tax_number as customer_tax_number,
                    bl.name as location_name,
                    bl.landmark,
                    bl.city as location_city,
                    bl.state as location_state,
                    bl.country as location_country,
                    bl.zip_code as location_zip,
                    bl.mobile as location_mobile,
                    bl.email as location_email,
                    b.name as business_name,
                    u.first_name as seller_first_name,
                    u.last_name as seller_last_name,
                    u.username as seller_username
                FROM transactions t
                LEFT JOIN contacts c ON t.contact_id = c.id
                LEFT JOIN business_locations bl ON t.location_id = bl.id
                LEFT JOIN business b ON bl.business_id = b.id
                LEFT JOIN users u ON t.created_by = u.id
                WHERE t.id = ? AND t.type IN ('sell', 'quotation', 'draft')
            `,
                [quotationId]
            );

            if (quotationRows.length === 0) {
                return null;
            }

            const quotation = quotationRows[0];

            // For billing receipts (IPAY), fetch line items from the linked VT transaction
            // IPAY transactions don't have their own line items - they share with the linked VT
            let lineItemsTransactionId = quotationId;
            
            if (quotation.invoice_no && quotation.invoice_no.startsWith('IPAY')) {
                // Try to get linked VT transaction ID
                // Priority: transfer_parent_id > linked_tax_invoice_id > find by ref_no
                if (quotation.transfer_parent_id) {
                    lineItemsTransactionId = quotation.transfer_parent_id;
                    console.log(`IPAY ${quotation.invoice_no}: Using transfer_parent_id ${lineItemsTransactionId} for line items`);
                } else if (quotation.linked_tax_invoice_id) {
                    lineItemsTransactionId = quotation.linked_tax_invoice_id;
                    console.log(`IPAY ${quotation.invoice_no}: Using linked_tax_invoice_id ${lineItemsTransactionId} for line items`);
                } else if (quotation.ref_no) {
                    // Try to find VT by ref_no (which stores the VT invoice number)
                    const [vtRows] = await connection.execute(
                        `SELECT id FROM transactions WHERE invoice_no = ? LIMIT 1`,
                        [quotation.ref_no]
                    );
                    if (vtRows.length > 0) {
                        lineItemsTransactionId = vtRows[0].id;
                        console.log(`IPAY ${quotation.invoice_no}: Found VT by ref_no ${quotation.ref_no}, using ID ${lineItemsTransactionId} for line items`);
                    }
                }
            }

            // Get quotation line items (only main products, not sub-products)
            const [lineItems] = await connection.execute(
                `
                SELECT 
                    tsl.*,
                    p.name as product_name,
                    p.product_description,
                    p.sku,
                    p.image as product_image,
                    v.name as variation_name,
                    u.short_name as unit_name,
                    pi.filename as additional_image,
                    pi.path as additional_image_path
                FROM transaction_sell_lines tsl
                LEFT JOIN products p ON tsl.product_id = p.id
                LEFT JOIN variations v ON tsl.variation_id = v.id
                LEFT JOIN units u ON p.unit_id = u.id
                LEFT JOIN product_images pi ON p.id = pi.id AND pi.business_id = p.business_id
                WHERE tsl.transaction_id = ? AND tsl.parent_sell_line_id IS NULL
                ORDER BY tsl.id
            `,
                [lineItemsTransactionId]
            );

            console.log(`Transaction ${quotation.invoice_no} (ID: ${quotationId}): Fetched ${lineItems.length} line items from transaction ID ${lineItemsTransactionId}`);

            return { quotation, lineItems };
        } finally {
            await connection.end();
        }
    }

    static async getQuotationByInvoiceNumber(invoiceNumber, req = null) {
        const dbConfig = this.getDbConfig(req);
        const connection = await mysql.createConnection(dbConfig);

        try {
            // Convert URL format to database format
            // ipay2025-10044 -> IPAY2025/10044
            // vt2025-0926 -> VT2025/0926
            let dbInvoiceNumber = invoiceNumber;
            
            // Check if it contains a dash and convert to slash format
            if (invoiceNumber.includes('-')) {
                dbInvoiceNumber = invoiceNumber.replace('-', '/').toUpperCase();
            }
            
            // Get quotation data by invoice number (try both formats)
            const [quotationRows] = await connection.execute(
                `
                SELECT 
                    t.*,
                    c.name as customer_name,
                    c.mobile as customer_mobile,
                    c.email as customer_email,
                    c.address_line_1,
                    c.address_line_2,
                    c.city,
                    c.state,
                    c.country,
                    c.zip_code,
                    c.supplier_business_name,
                    c.contact_id as customer_contact_id,
                    c.tax_number as customer_tax_number,
                    bl.name as location_name,
                    bl.landmark,
                    bl.city as location_city,
                    bl.state as location_state,
                    bl.country as location_country,
                    bl.zip_code as location_zip,
                    bl.mobile as location_mobile,
                    bl.email as location_email,
                    b.name as business_name,
                    u.first_name as seller_first_name,
                    u.last_name as seller_last_name,
                    u.username as seller_username
                FROM transactions t
                LEFT JOIN contacts c ON t.contact_id = c.id
                LEFT JOIN business_locations bl ON t.location_id = bl.id
                LEFT JOIN business b ON bl.business_id = b.id
                LEFT JOIN users u ON t.created_by = u.id
                WHERE t.invoice_no = ? OR t.invoice_no = ?
                LIMIT 1
                `,
                [invoiceNumber, dbInvoiceNumber]
            );

            if (quotationRows.length === 0) {
                return null;
            }

            const quotation = quotationRows[0];

            // For billing receipts (IPAY), fetch line items from the linked VT transaction
            // IPAY transactions don't have their own line items - they share with the linked VT
            let lineItemsTransactionId = quotation.id;
            
            if (quotation.invoice_no && quotation.invoice_no.startsWith('IPAY')) {
                // Try to get linked VT transaction ID
                // Priority: transfer_parent_id > linked_tax_invoice_id > find by ref_no
                if (quotation.transfer_parent_id) {
                    lineItemsTransactionId = quotation.transfer_parent_id;
                    console.log(`IPAY ${quotation.invoice_no}: Using transfer_parent_id ${lineItemsTransactionId} for line items`);
                } else if (quotation.linked_tax_invoice_id) {
                    lineItemsTransactionId = quotation.linked_tax_invoice_id;
                    console.log(`IPAY ${quotation.invoice_no}: Using linked_tax_invoice_id ${lineItemsTransactionId} for line items`);
                } else if (quotation.ref_no) {
                    // Try to find VT by ref_no (which stores the VT invoice number)
                    const [vtRows] = await connection.execute(
                        `SELECT id FROM transactions WHERE invoice_no = ? LIMIT 1`,
                        [quotation.ref_no]
                    );
                    if (vtRows.length > 0) {
                        lineItemsTransactionId = vtRows[0].id;
                        console.log(`IPAY ${quotation.invoice_no}: Found VT by ref_no ${quotation.ref_no}, using ID ${lineItemsTransactionId} for line items`);
                    }
                }
            }

            // Get line items with product images
            const [lineItemRows] = await connection.execute(
                `
                SELECT 
                    tsl.*,
                    p.name as product_name,
                    p.product_description,
                    p.sku,
                    p.image as product_image,
                    pv.name as variation_name,
                    u.short_name as unit_name,
                    pi.filename as additional_image,
                    pi.path as image_path
                FROM transaction_sell_lines tsl
                LEFT JOIN products p ON tsl.product_id = p.id
                LEFT JOIN variations pv ON tsl.variation_id = pv.id
                LEFT JOIN units u ON p.unit_id = u.id
                LEFT JOIN product_images pi ON p.id = pi.id AND pi.business_id = p.business_id
                WHERE tsl.transaction_id = ? AND tsl.parent_sell_line_id IS NULL
                ORDER BY tsl.id
                `,
                [lineItemsTransactionId]
            );

            console.log(`Transaction ${quotation.invoice_no} (ID: ${quotation.id}): Fetched ${lineItemRows.length} line items from transaction ID ${lineItemsTransactionId}`);

            return { quotation, lineItems: lineItemRows };
        } finally {
            await connection.end();
        }
    }

    static async testConnection(req = null) {
        try {
            const dbConfig = this.getDbConfig(req);
            const connection = await mysql.createConnection(dbConfig);
            await connection.execute('SELECT 1');
            await connection.end();
            return { success: true, message: 'Database connection successful', config: `${dbConfig.host}:${dbConfig.port}/${dbConfig.database}` };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }
}

module.exports = DatabaseService;
