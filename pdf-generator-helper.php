<?php

/**
 * PDF Generator Helper for RubyShop
 * This file helps integrate with the Node.js PDF generator service
 */

class PDFGeneratorHelper
{
    private $nodeServiceUrl;
    
    public function __construct($nodeServiceUrl = 'https://api-shop.rubyshop.co.th')
    {
        $this->nodeServiceUrl = $nodeServiceUrl;
    }
    
    /**
     * Generate PDF for quotation
     * 
     * @param int $quotationId
     * @return array Response with PDF data or error
     */
    public function generateQuotationPDF($quotationId)
    {
        $url = $this->nodeServiceUrl . '/public/quotations/' . $quotationId . '/pdf-print-nodejs';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // Include headers in response
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/pdf'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $error
            ];
        }
        
        if ($httpCode === 200) {
            // Separate headers and body
            $headers = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);
            
            // Extract filename from headers
            $filename = $this->extractFilenameFromHeaders($headers) ?: 'quotation-' . $quotationId . '.pdf';
            $cleanFilename = str_replace('.pdf', '', $filename); // Remove .pdf extension for URL
            
            // Save PDF to storage
            $storagePath = $this->savePDFToStorage($body, $cleanFilename);
            
            return [
                'success' => true,
                'pdf_data' => $body,
                'filename' => $filename,
                'storage_path' => $storagePath,
                'custom_url' => url($cleanFilename), // Custom URL like /vt2025-0922
                'clean_filename' => $cleanFilename
            ];
        } else {
            return [
                'success' => false,
                'error' => 'HTTP Error: ' . $httpCode,
                'response' => $response
            ];
        }
    }

    /**
     * Generate PDF for tax invoice
     * 
     * @param int $quotationId
     * @return array Response with PDF data or error
     */
    public function generateTaxInvoicePDF($quotationId)
    {
        $url = $this->nodeServiceUrl . '/tax-invoice/' . $quotationId . '/pdf-print-nodejs';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // Include headers in response
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/pdf'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $error
            ];
        }
        
        if ($httpCode === 200) {
            // Separate headers and body
            $headers = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);
            
            // Extract filename from headers
            $filename = $this->extractFilenameFromHeaders($headers) ?: 'tax-invoice-' . $quotationId . '.pdf';
            $cleanFilename = str_replace('.pdf', '', $filename); // Remove .pdf extension for URL
            
            // Save PDF to storage
            $storagePath = $this->savePDFToStorage($body, $cleanFilename);
            
            return [
                'success' => true,
                'pdf_data' => $body,
                'filename' => $filename,
                'storage_path' => $storagePath,
                'custom_url' => url($cleanFilename), // Custom URL like /vt2025-0922
                'clean_filename' => $cleanFilename
            ];
        } else {
            return [
                'success' => false,
                'error' => 'HTTP Error: ' . $httpCode,
                'response' => $response
            ];
        }
    }

    /**
     * Generate PDF for billing receipt
     * 
     * @param int $quotationId
     * @return array Response with PDF data or error
     */
    public function generateBillingReceiptPDF($quotationId)
    {
        $url = $this->nodeServiceUrl . '/billing-receipt/' . $quotationId . '/pdf-print-nodejs';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // Include headers in response
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/pdf'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $error
            ];
        }
        
        if ($httpCode === 200) {
            // Separate headers and body
            $headers = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);
            
            // Extract filename from headers
            $filename = $this->extractFilenameFromHeaders($headers) ?: 'billing-receipt-' . $quotationId . '.pdf';
            $cleanFilename = str_replace('.pdf', '', $filename); // Remove .pdf extension for URL
            
            // Save PDF to storage
            $storagePath = $this->savePDFToStorage($body, $cleanFilename);
            
            return [
                'success' => true,
                'pdf_data' => $body,
                'filename' => $filename,
                'storage_path' => $storagePath,
                'custom_url' => url($cleanFilename), // Custom URL like /vt2025-0922
                'clean_filename' => $cleanFilename
            ];
        } else {
            return [
                'success' => false,
                'error' => 'HTTP Error: ' . $httpCode,
                'response' => $response
            ];
        }
    }
    
    /**
     * Extract filename from response headers
     * 
     * @param string $headers
     * @return string|null
     */
    private function extractFilenameFromHeaders($headers)
    {
        // Look for X-Suggested-Filename header
        if (preg_match('/X-Suggested-Filename:\s*(.+)/i', $headers, $matches)) {
            return trim($matches[1]);
        }
        
        // Fallback: look for Content-Disposition filename
        if (preg_match('/filename="([^"]+)"/i', $headers, $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }
    
    /**
     * Save PDF data to storage and return the file path
     * 
     * @param string $pdfData
     * @param string $filename
     * @return string|null
     */
    private function savePDFToStorage($pdfData, $filename)
    {
        try {
            // Create storage directory if it doesn't exist
            $storageDir = storage_path('app/public/temp-pdfs');
            if (!file_exists($storageDir)) {
                mkdir($storageDir, 0755, true);
            }
            
            // Clean filename for filesystem
            $safeFilename = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $filename);
            $filePath = $storageDir . '/' . $safeFilename . '.pdf';
            
            // Save PDF data to file
            file_put_contents($filePath, $pdfData);
            
            return $filePath;
        } catch (Exception $e) {
            error_log('Error saving PDF: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if Node.js service is running
     * 
     * @return bool
     */
    public function isServiceRunning()
    {
        $url = $this->nodeServiceUrl . '/health';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }
}

/**
 * Example usage in your Laravel controller:
 * 
 * Route: /quotations/{id}/pdf-print-nodejs
 */

// Example Controller Method
function generateNodeJSPDF($quotationId)
{
    $pdfGenerator = new PDFGeneratorHelper();
    
    // Check if service is running
    if (!$pdfGenerator->isServiceRunning()) {
        return response()->json([
            'error' => 'PDF Generator service is not running. Please start the Node.js server.'
        ], 503);
    }
    
    // Generate PDF
    $result = $pdfGenerator->generateQuotationPDF($quotationId);
    
    if ($result['success']) {
        return response($result['pdf_data'])
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"');
    } else {
        return response()->json([
            'error' => $result['error']
        ], 500);
    }
}

?>