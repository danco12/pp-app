<?php 
namespace App\Model;

use Nette;

/////////////////////////////////////////////
//raz za hodinu update jobov a firiem!!!!! //
/////////////////////////////////////////////
/**
 * @property-read void $import
 */
class ImportService
{
    use Nette\SmartObject;
    
    /**
     * @var Nette\Database\Context
     */
    private $database;

    private $mathOperationsArray = [
        "h" => [
            "operator" => "*",
            "number" => 160
        ],
        "d" => [
            "operator" => "*",
            "number" => 20
        ],
        "w" => [
            "operator" => "*",
            "number" => 4
        ],
        "m" => [
            "operator" => "*",
            "number" => 1
        ],
        "y" => [
            "operator" => "/",
            "number" => 12
        ]
    ];


    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }


    public function getImport()
    {
        self::importCompanies();
        self::importJobs();
    }

    
    private function getJobs()
    {
        return $this->database->table("jobs");
    }

    
    private function getCompanies()
    {
        return $this->database->table("companies");
    }

    
    private function getLocations()
    {
        return $this->database->table("job_location");
    }

    
    private function importCompanies()
    {
        $xml_data = file_get_contents("https://jobangels.com/export/companies");
        $companies = simplexml_load_string($xml_data);
        $updated = date("Y-m-d H:i:s");
        $to_insert = [];

        foreach($companies as $company) {
            $id = (string)$company->attributes()["id"];
            $data = [
                "name" => (string)$company->name,
                "address" => (string)$company->address,
                "city" => (string)$company->city,
                "description" => htmlspecialchars_decode((string)$company->description),
                "logo" => (string)$company->logoUrl,
                "email" => (string)$company->email,
                "phone" => (string)$company->phone,
                "website" => (string)$company->website,
                "updated" => $updated
            ];

            $c_row = self::getCompanies()->where("id", $id)->fetch();

            if ($c_row) {
                $c_row->update($data);
            } else {
                $to_insert[] = array_merge(["id" => $id], $data);
            }
        }

        if (!empty($to_insert)) {
            self::getCompanies()->insert($to_insert);
        }
    }

    
    private function importJobs()
    {
        $xml_data = file_get_contents("https://jobangels.com/export/dano");
        $jobs = simplexml_load_string($xml_data);
        $updated = date("Y-m-d H:i:s");

        foreach($jobs as $job) {
            if (trim((string) $job->salaryMin) != "") {
                $data = [
                    "company_id" => (string) $job->companyId,
                    "name" => (string) $job->name,
                    "key" => (string) $job->key,
                    "percentil" => (string) $job->percentil,
                    "link" => (string) $job->link,
                    "title_image" => (string) $job->titleImage,
                    "field_of_work_id" => (string) $job->fieldOfWorkId,
                    "publication_date" => (string) $job->publicationDate,
                    "expiration_date" => (trim((string) $job->expirationDate) == "") ? null : (string) $job->expirationDate,
                    "hire_date" => (trim((string) $job->hireDate) == "") ? null : (string) $job->hireDate,
                    "contract_type_id" => (trim((string) $job->contractTypeId) == "") ? null : (string) $job->contractTypeId,
                    "job_description" => htmlspecialchars_decode((trim((string) $job->jobDescription) == "") ? null : (string) $job->jobDescription),
                    "development_opportunities" => htmlspecialchars_decode((trim((string) $job->developmentOpportunities) == "") ? null : (string) $job->developmentOpportunities),
                    "next_career_step" => htmlspecialchars_decode((trim((string) $job->nextCareerStep) == "") ? null : (string) $job->nextCareerStep),
                    "other_info" => htmlspecialchars_decode((trim((string) $job->otherInfo) == "") ? null : (string) $job->otherInfo),
                    "salary_min" => (trim((string) $job->salaryMin) == "") ? null : (string) $job->salaryMin,
                    "salary_max" => (trim((string) $job->salaryMax) == "") ? null : (string) $job->salaryMax,
                    "currency" => (trim((string) $job->currency) == "") ? null : (string) $job->currency,
                    "salary_time_unit" => (trim((string) $job->salaryTimeUnit) == "") ? null : (string) $job->salaryTimeUnit,
                    "salary_info" => htmlspecialchars_decode((trim((string) $job->salaryInfo) == "") ? null : (string) $job->salaryInfo),
                    "other_benefits" => htmlspecialchars_decode((trim((string) $job->otherBenefits) == "") ? null : (string) $job->otherBenefits),
                    "updated" => $updated
                ];

                $mathOperation = $this->mathOperationsArray[$data["salary_time_unit"]];
                if ($data["salary_min"] != "") {
                    $data["salary_order_min"] = self::salaryOrderCompute($data["salary_min"],$mathOperation,$data["currency"]);
                }

                if ($data["salary_max"] != "") {
                    $data["salary_order_max"] = self::salaryOrderCompute($data["salary_max"],$mathOperation,$data["currency"]);
                }
                
                $job_row = self::getJobs()->where("key", (string)$job->key)->fetch();
                if ($job_row) {
                    self::getJobs()->where("key", (string)$job->key)->update($data);
                } else {
                    self::getJobs()->insert($data);
                    $job_row = self::getJobs()->where("key", (string)$job->key)->fetch();
                }

                ////////////////
                // locations  //
                ////////////////
                $locations = [];
                self::getLocations()->where("job_id", $job_row->id)->update([
                    "closed" => 1
                ]);
               
                foreach($job->locations->location as $row) {
                    $data = [
                        "job_id" => $job_row->id,
                        "location" => (string)$row
                    ];
                    $location = self::getLocations()->where($data)->fetch();

                    if ($location) {
                        $location->update([
                            "closed" => 0
                        ]);
                    } else {
                        $locations[] = array_merge($data, ["closed" => 0]);
                    }
                }

                if (!empty($locations)){
                    self::getLocations()->insert($locations);
                }

                ////////////////////
                // qualifications //
                ////////////////////
                self::saveQualifications($job->qualifications->required, $job_row->id, "required");
                // self::saveQualifications($job->qualifications->preferred, $job_row->id, "preferred");

                //////////////////////
                // responsibilities //
                //////////////////////
                self::saveResponsibilities($job->responsibilities, $job_row->id);

                //////////////////
                // top benefits //
                //////////////////
                self::saveBenefits($job->topBenefits, $job_row->id);

                /////////////
                // gallery //
                /////////////
                self::savePhotos($job->photos, $job_row->id);
            }
            
            self::getJobs()->where([
                "updated < ? OR updated IS NULL" => $updated,
                "expiration_date IS NULL"
            ])->update([
                "expiration_date" => $updated
            ]);
        }
    }

    
    private function saveQualifications($qualifications, $job_id, $type)
    {
        $this->database->table("job_years_of_prax")->where([
            "job_id" => $job_id,
            "type" => $type
        ])->delete();
       
        foreach ($qualifications->yearsOfPrax as $row) {
            if ((string) $row != ""){
                $this->database->table("job_years_of_prax")->insert([
                    "job_id" => $job_id,
                    "years" => (string) $row,
                    "type" => $type
                ]);
            }
        }

        $this->database->table("job_education")->where([
            "job_id" => $job_id,
            "type" => $type
        ])->delete();
       
        foreach ($qualifications->educationLevelId as $row) {
            if ((string) $row != ""){
                $this->database->table("job_education")->insert([
                    "job_id" => $job_id,
                    "education_level_id" => (string) $row,
                    "type" => $type
                ]);
            }
        }

        $this->database->table("job_field_of_study")->where([
            "job_id" => $job_id,
            "type" => $type
        ])->delete();
       
        foreach ($qualifications->fieldsOfStudy as $row) {
            if ((string) $row->fieldId != ""){
                $this->database->table("job_field_of_study")->insert([
                    "job_id" => $job_id,
                    "field_of_study_id" => (string) $row->fieldId,
                    "type" => $type
                ]);
            }
        }

        $this->database->table("job_field_of_work")->where([
            "job_id" => $job_id,
            "type" => $type
        ])->delete();

        foreach ($qualifications->fieldsOfWork as $row) {
            if ((string) $row->fieldOfWork->fieldId != "") {
                $this->database->table("job_field_of_work")->insert([
                    "job_id" => $job_id,
                    "field_of_work_id" => (string) $row->fieldOfWork->fieldId,
                    "years" => ((string) $row->fieldOfWork->years == "") ? 0 : (string) $row->fieldOfWork->years,
                    "type" => $type
                ]);
            }
        }

        $this->database->table("job_language")->where([
            "job_id" => $job_id,
            "type" => $type
        ])->delete();

        foreach ($qualifications->languages as $row) {
            if ((string) $row->lang->langId != "") {
                $this->database->table("job_language")->insert([
                    "job_id" => $job_id,
                    "language_id" => (string) $row->lang->langId,
                    "language_level_id" => $row->lang->levelId,
                    "type" => $type
                ]);
            }
        }

        $this->database->table("job_skill")->where([
            "job_id" => $job_id,
            "type" => $type
        ])->delete();

        foreach ($qualifications->skills as $row) {
            if ((string) $row->skill != "") {
                $this->database->table("job_skill")->insert([
                    "job_id" => $job_id,
                    "name" => (string) $row->skill,
                    "type" => $type
                ]);
            }
        }

        $this->database->table("job_qualification_other")->where([
            "job_id" => $job_id,
            "type" => $type
        ])->delete();
       
        foreach ($qualifications->other as $row) {
            if ((string) $row != "") {
                $this->database->table("job_qualification_other")->insert([
                    "job_id" => $job_id,
                    "text" => (string) $row,
                    "type" => $type
                ]);
            }
        }
    }

    
    public function saveResponsibilities($responsibilities, $job_id)
    {
        $this->database->table("job_responsibility")->where([
            "job_id" => $job_id
        ])->delete();
    
        foreach ($responsibilities->respo as $row) {
            if ((string) $row->name != "") {
                $this->database->table("job_responsibility")->insert([
                    "job_id" => $job_id,
                    "name" => (string) $row->name,
                    "percentage" => (string) $row->percentage
                ]);
            }
        }
    }

    
    private function saveBenefits($benefits, $job_id)
    {
        $this->database->table("job_top_benefit")->where([
            "job_id" => $job_id
        ])->delete();
    
        foreach ($benefits->benefit as $row) {
            if ((string) $row->beneftiId != "") {
                $this->database->table("job_top_benefit")->insert([
                    "job_id" => $job_id,
                    "top_benefit_id" => (string) $row->beneftiId,
                    "order" => (string) $row->order
                ]);
            }
        }
    }

    
    //job_gallery
    private function savePhotos($photos, $job_id)
    {
        $this->database->table("job_gallery")->where([
            "job_id" => $job_id
        ])->delete();
        foreach ($photos->photo as $row) {
            if ((string) $row != "") {
                $this->database->table("job_gallery")->insert([
                    "job_id" => $job_id,
                    "photo_url" => (string) $row,
                ]);
            }
        }
    }

    
    private function getExchangeRates()
    {
        return $this->database->table("exchange_rate");
    }

    
    private function exchangeNumberToEUR($currency, $amount)
    {
        $row = self::getExchangeRates()->where("currency", $currency)->fetch();

        if ($row){
            return $amount / $row->rate;
        }else{
            return $amount;
        }
    }

    
    private function salaryOrderCompute($value, $mathOperation, $currency)
    {
        if (empty($value)) {
            $value = 0;
        }

        $mathOperationString = $value . $mathOperation["operator"] . $mathOperation["number"];
        eval('$answer ='.$mathOperationString.';');

        return self::exchangeNumberToEUR($currency, $answer);
    }
}