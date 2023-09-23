<?php
/**
 * Plugin Name: Job offers - praca.gov.pl
 * Description: Automatic downloading of job offers from the serwis.praca.gov.pl portal using WebService
 * Version: 1.0.1
 * Author: ZemeStudioPL
 * Author URI: https://zemestudio.pl
 * Text Domain: job-offers
 */


defined('ABSPATH') or die('No direct access allowed.');

class CustomJobImporter {

    public function __construct() {

        $this->plugin_options = get_option('custom_job_importer_options', array());
       
        $plugin_dir = plugin_dir_path(__FILE__); // Get the plugin directory path
        ini_set('soap.wsdl_cache_enabled', '1');
        ini_set('soap.wsdl_cache_dir', $plugin_dir . 'soap_logs/'); // Use the plugin directory path for SOAP logs
        $wsdl_url = 'http://oferty.praca.gov.pl/integration/services/v2/oferta?wsdl'; // Replace with the actual WSDL URL
        $this->soap_client = new SoapClient($wsdl_url, array('soap_version' => SOAP_1_2));
        $this->add_settings_page();

    }

    public function get_all_offe() {
        
        $this->get_web_service_data();

        $mimeFilePath = __DIR__ . '/response.txt'; 
        $targetDirectory = __DIR__ . '/extracted_files';
        
        $result = $this->extractCompressedFileFromMIME($mimeFilePath, $targetDirectory);
        if ($result !== false) {
            
        } else {
            echo "Error extracting the file.";
        }

    }

    public function extractCompressedFileFromMIME($filePath, $targetDirectory) {
   
        $postData = file_get_contents($filePath);

        // Find the boundary identifier from the content type
        preg_match('/^--uuid:(.+)$/m', $postData, $matches);
        $boundary = '--uuid:' . trim($matches[1]);

        // Separate the parts using the boundary
        $parts = explode($boundary, $postData);

        // Search for the attachment part with Content-Type: application/octet-stream
        $attachmentData = null;
        foreach ($parts as $part) {
            if (strpos($part, 'Content-Type: application/octet-stream') !== false) {
                $attachmentData = $part;
                break;
            }
        }

        if ($attachmentData === null) {
            // Handle the error if the attachment part is not found.
            return false;
        } else {
            
            $binaryData = substr($attachmentData, strpos($attachmentData, "\r\n\r\n") + 4);

            // Save the binary data to a temporary zip file
            $tempZipFile = tempnam(sys_get_temp_dir(), 'extracted_zip');
            file_put_contents($tempZipFile, $binaryData);

            // Create the target directory if it doesn't exist
            if (!is_dir($targetDirectory)) {
                mkdir($targetDirectory, 0777, true);
            }

            // Extract the contents of the zip file to the target directory
            $zip = new ZipArchive();
            if ($zip->open($tempZipFile) === true) {
                $zip->extractTo($targetDirectory);
                $zip->close();

                // Delete the temporary zip file
                unlink($tempZipFile);

                return $targetDirectory; // Return the path to the extracted files directory on success
            } else {
                // Handle the error if the zip file cannot be opened or extracted.
                return false;
            }
        }
    }

    private function get_web_service_data() {
        $url = 'https://oferty.praca.gov.pl/integration/services/v2/oferta?wsdl';

        $soapEnvelope = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ofer="http://oferty.praca.gov.pl/v2/oferta">
              <soapenv:Header/>
              <soapenv:Body>
                <ofer:Dane>
                  <pytanie>
                    <Partner>PARTNER-NAME</Partner>
                    <Jezyk>pl</Jezyk>
                    <Kryterium>
                      <Wszystkie>true</Wszystkie>
                    </Kryterium>
                  </pytanie>
                </ofer:Dane>
              </soapenv:Body>
            </soapenv:Envelope>';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_VERBOSE, true);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $soapEnvelope);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
        curl_setopt($curl, CURLOPT_BINARYTRANSFER, true); // Treat response data as binary
        curl_setopt($curl, CURLOPT_ENCODING, ''); // Handle any compression applied to the response

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            echo 'cURL Error: ' . curl_error($curl);
            return;
        }

        curl_close($curl);

        // Save the response to a .txt file
        $filename = __DIR__ . '/response.txt'; // Replace this with the desired filename
        file_put_contents($filename, $response);

        return true;

    }
}

$auto_run_plugin = new CustomJobImporter();