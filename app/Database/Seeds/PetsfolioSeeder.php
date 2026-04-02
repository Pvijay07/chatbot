<?php

namespace App\Database\Seeds;

use App\Models\InsuranceDocumentChunkModel;
use App\Models\InsuranceDocumentModel;
use App\Models\InsurancePlanModel;
use App\Models\UserModel;
use CodeIgniter\Database\Seeder;

class PetsfolioSeeder extends Seeder
{
    public function run()
    {
        $userModel = new UserModel();
        $planModel = new InsurancePlanModel();
        $documentModel = new InsuranceDocumentModel();
        $chunkModel = new InsuranceDocumentChunkModel();

        $admin = $userModel->findByEmail('admin@petsfolio.local');
        if ($admin === null) {
            $adminId = $userModel->insert([
                'name'             => 'Petsfolio Admin',
                'email'            => 'admin@petsfolio.local',
                'password_hash'    => password_hash('Password123!', PASSWORD_DEFAULT),
                'role'             => 'admin',
                'preferred_locale' => 'en',
            ], true);
        } else {
            $adminId = (int) $admin['id'];
        }

        if ($userModel->findByEmail('user@petsfolio.local') === null) {
            $userModel->insert([
                'name'             => 'Petsfolio User',
                'email'            => 'user@petsfolio.local',
                'password_hash'    => password_hash('Password123!', PASSWORD_DEFAULT),
                'role'             => 'user',
                'preferred_locale' => 'en',
            ]);
        }

        if ($planModel->countAllResults() === 0) {
            $planModel->insertBatch($this->plans());
        }

        if ($documentModel->countAllResults() > 0) {
            return;
        }

        foreach ($this->documents() as $document) {
            $documentId = $documentModel->insert([
                'title'        => $document['title'],
                'file_name'    => $document['file_name'],
                'file_path'    => null,
                'mime_type'    => 'text/plain',
                'language'     => $document['language'],
                'content_hash' => hash('sha256', $document['content']),
                'uploaded_by'  => $adminId,
                'is_active'    => 1,
            ], true);

            $chunks = preg_split('/\n\s*\n/', trim($document['content'])) ?: [];
            foreach (array_values($chunks) as $index => $chunk) {
                $chunkModel->insert([
                    'document_id' => $documentId,
                    'chunk_index' => $index,
                    'language'    => $document['language'],
                    'content'     => trim($chunk),
                    'token_count' => str_word_count(strip_tags($chunk)),
                    'keywords'    => implode(', ', $this->extractKeywords($chunk)),
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    private function plans(): array
    {
        return [
            [
                'pet_type'              => 'dog',
                'slug'                  => 'dog-secure-start',
                'name_en'               => 'Secure Start Dog',
                'name_hi'               => 'सिक्योर स्टार्ट डॉग',
                'summary_en'            => 'Best for new dog parents who want accident cover, diagnostics, and moderate reimbursement at an affordable monthly premium.',
                'summary_hi'            => 'नए डॉग पेरेंट्स के लिए किफायती प्लान, जिसमें दुर्घटना कवर, डायग्नोस्टिक्स और मध्यम रीइम्बर्समेंट शामिल है।',
                'price_monthly'         => 28.00,
                'annual_limit'          => 5000,
                'deductible'            => 250,
                'reimbursement_percent' => 70,
                'waiting_period_days'   => 14,
                'claim_steps_en'        => '1. Visit a licensed veterinarian. 2. Pay the invoice. 3. Upload the bill and clinical notes within 90 days. 4. Petsfolio reviews the claim within 3 business days.',
                'claim_steps_hi'        => '1. लाइसेंस प्राप्त पशु-चिकित्सक के पास जाएं। 2. बिल का भुगतान करें। 3. 90 दिनों के भीतर बिल और क्लिनिकल नोट्स अपलोड करें। 4. Petsfolio 3 कार्यदिवस में क्लेम की समीक्षा करता है।',
                'exclusions_en'         => 'Pre-existing conditions, elective grooming, breeding costs, and waiting-period incidents are excluded.',
                'exclusions_hi'         => 'पहले से मौजूद बीमारी, ग्रूमिंग, ब्रीडिंग खर्च और वेटिंग पीरियड के दौरान हुई घटना कवर नहीं होती।',
                'is_active'             => 1,
            ],
            [
                'pet_type'              => 'dog',
                'slug'                  => 'dog-care-plus',
                'name_en'               => 'Care Plus Dog',
                'name_hi'               => 'केयर प्लस डॉग',
                'summary_en'            => 'Balanced protection for dogs needing accident and illness cover, pharmacy reimbursement, and higher annual limits.',
                'summary_hi'            => 'कुत्तों के लिए संतुलित सुरक्षा, जिसमें दुर्घटना और बीमारी कवर, दवा रीइम्बर्समेंट और अधिक वार्षिक सीमा शामिल है।',
                'price_monthly'         => 46.00,
                'annual_limit'          => 10000,
                'deductible'            => 200,
                'reimbursement_percent' => 80,
                'waiting_period_days'   => 10,
                'claim_steps_en'        => '1. Submit the invoice, diagnosis, and discharge notes. 2. Add bank details once. 3. Petsfolio confirms document completeness in 24 hours. 4. Approved claims are paid within 2 business days.',
                'claim_steps_hi'        => '1. बिल, डायग्नोसिस और डिस्चार्ज नोट्स जमा करें। 2. बैंक विवरण एक बार जोड़ें। 3. Petsfolio 24 घंटे में दस्तावेज़ की पुष्टि करता है। 4. स्वीकृत क्लेम 2 कार्यदिवस में भुगतान होते हैं।',
                'exclusions_en'         => 'Cosmetic procedures, routine boarding, non-prescribed supplements, and conditions diagnosed before activation are excluded.',
                'exclusions_hi'         => 'कॉस्मेटिक प्रक्रियाएं, रूटीन बोर्डिंग, बिना प्रिस्क्रिप्शन सप्लीमेंट और एक्टिवेशन से पहले निदान की गई स्थितियां कवर नहीं होतीं।',
                'is_active'             => 1,
            ],
            [
                'pet_type'              => 'dog',
                'slug'                  => 'dog-elite-shield',
                'name_en'               => 'Elite Shield Dog',
                'name_hi'               => 'एलीट शील्ड डॉग',
                'summary_en'            => 'Highest coverage for senior or high-risk dogs, with specialist visits, surgery support, and premium reimbursement.',
                'summary_hi'            => 'सीनियर या हाई-रिस्क कुत्तों के लिए अधिकतम कवर, जिसमें स्पेशलिस्ट विजिट, सर्जरी सपोर्ट और प्रीमियम रीइम्बर्समेंट है।',
                'price_monthly'         => 69.00,
                'annual_limit'          => 20000,
                'deductible'            => 150,
                'reimbursement_percent' => 90,
                'waiting_period_days'   => 7,
                'claim_steps_en'        => '1. Ask the vet for treatment notes and itemized bills. 2. Upload scans or photos. 3. Emergency claims are prioritized the same day. 4. Complex claims receive a nurse callback if more information is needed.',
                'claim_steps_hi'        => '1. वेट से ट्रीटमेंट नोट्स और आइटमाइज्ड बिल लें। 2. स्कैन या फोटो अपलोड करें। 3. इमरजेंसी क्लेम उसी दिन प्राथमिकता से देखे जाते हैं। 4. जटिल क्लेम में नर्स कॉल-बैक किया जाता है।',
                'exclusions_en'         => 'Experimental therapy, non-medical travel, pet daycare, and conditions documented before enrollment are excluded.',
                'exclusions_hi'         => 'एक्सपेरिमेंटल थेरेपी, गैर-चिकित्सीय यात्रा, पेट डे-केयर और एनरोलमेंट से पहले दर्ज स्थितियां कवर नहीं होतीं।',
                'is_active'             => 1,
            ],
            [
                'pet_type'              => 'cat',
                'slug'                  => 'cat-secure-start',
                'name_en'               => 'Secure Start Cat',
                'name_hi'               => 'सिक्योर स्टार्ट कैट',
                'summary_en'            => 'Entry plan for indoor and young cats with accident cover and simple digital claims.',
                'summary_hi'            => 'इनडोर और युवा बिल्लियों के लिए एंट्री प्लान, जिसमें दुर्घटना कवर और आसान डिजिटल क्लेम हैं।',
                'price_monthly'         => 22.00,
                'annual_limit'          => 4000,
                'deductible'            => 200,
                'reimbursement_percent' => 70,
                'waiting_period_days'   => 14,
                'claim_steps_en'        => '1. Save the vet receipt. 2. Submit the receipt and diagnosis within 90 days. 3. Petsfolio pays eligible claims to your bank account after review.',
                'claim_steps_hi'        => '1. वेट रसीद संभालकर रखें। 2. 90 दिनों के भीतर रसीद और डायग्नोसिस जमा करें। 3. समीक्षा के बाद पात्र क्लेम बैंक खाते में भेजे जाते हैं।',
                'exclusions_en'         => 'Dental cleaning, litter, food, mating expenses, and pre-existing illness are excluded.',
                'exclusions_hi'         => 'डेंटल क्लीनिंग, लिटर, भोजन, मैथिंग खर्च और पहले से मौजूद बीमारी कवर नहीं होती।',
                'is_active'             => 1,
            ],
            [
                'pet_type'              => 'cat',
                'slug'                  => 'cat-care-plus',
                'name_en'               => 'Care Plus Cat',
                'name_hi'               => 'केयर प्लस कैट',
                'summary_en'            => 'Recommended for cats needing illness cover, imaging, blood tests, and strong reimbursement.',
                'summary_hi'            => 'बीमारी कवर, इमेजिंग, ब्लड टेस्ट और मजबूत रीइम्बर्समेंट चाहने वाली बिल्लियों के लिए सुझाया गया प्लान।',
                'price_monthly'         => 35.00,
                'annual_limit'          => 8000,
                'deductible'            => 175,
                'reimbursement_percent' => 80,
                'waiting_period_days'   => 10,
                'claim_steps_en'        => '1. Attach lab reports and invoice. 2. Complete the symptom timeline. 3. Petsfolio confirms if any more records are needed. 4. Payment is sent after approval.',
                'claim_steps_hi'        => '1. लैब रिपोर्ट और बिल संलग्न करें। 2. लक्षणों की टाइमलाइन भरें। 3. Petsfolio बताता है कि और रिकॉर्ड चाहिए या नहीं। 4. स्वीकृति के बाद भुगतान भेजा जाता है।',
                'exclusions_en'         => 'Preventive vaccines, cosmetic surgery, food intolerance without diagnosis, and pre-policy disease are excluded.',
                'exclusions_hi'         => 'प्रिवेंटिव वैक्सीन, कॉस्मेटिक सर्जरी, बिना निदान फूड इंटॉलरेंस और पॉलिसी से पहले की बीमारी कवर नहीं होती।',
                'is_active'             => 1,
            ],
            [
                'pet_type'              => 'cat',
                'slug'                  => 'cat-elite-shield',
                'name_en'               => 'Elite Shield Cat',
                'name_hi'               => 'एलीट शील्ड कैट',
                'summary_en'            => 'Premium cat plan with specialist support, emergency hospitalization, and the highest annual limit.',
                'summary_hi'            => 'प्रीमियम कैट प्लान, जिसमें स्पेशलिस्ट सपोर्ट, इमरजेंसी हॉस्पिटलाइजेशन और सबसे ऊंची वार्षिक सीमा है।',
                'price_monthly'         => 52.00,
                'annual_limit'          => 15000,
                'deductible'            => 125,
                'reimbursement_percent' => 90,
                'waiting_period_days'   => 7,
                'claim_steps_en'        => '1. Submit emergency admission note and invoice. 2. Track review status in the dashboard. 3. High-value claims may request one additional medical record. 4. Approved payouts are released quickly.',
                'claim_steps_hi'        => '1. इमरजेंसी एडमिशन नोट और बिल जमा करें। 2. डैशबोर्ड में रिव्यू स्टेटस देखें। 3. बड़े क्लेम में एक अतिरिक्त मेडिकल रिकॉर्ड मांगा जा सकता है। 4. स्वीकृत भुगतान तेजी से जारी होते हैं।',
                'exclusions_en'         => 'Breeding support, routine claw care, experimental therapy, and known conditions before policy start are excluded.',
                'exclusions_hi'         => 'ब्रीडिंग सपोर्ट, रूटीन क्लॉ केयर, एक्सपेरिमेंटल थेरेपी और पॉलिसी शुरू होने से पहले की ज्ञात स्थितियां कवर नहीं होतीं।',
                'is_active'             => 1,
            ],
        ];
    }

    private function documents(): array
    {
        return [
            [
                'title'     => 'Petsfolio General Policy Handbook',
                'file_name' => 'seed-general-policy.txt',
                'language'  => 'en',
                'content'   => "Petsfolio pet insurance covers eligible accidents, illnesses, diagnostics, surgery, prescription medication, and hospitalization based on the plan chosen. Reimbursement is applied after the deductible. Pre-existing conditions are never covered.\n\nWaiting periods apply to new policies. Secure Start plans have a 14 day waiting period, Care Plus plans have a 10 day waiting period, and Elite Shield plans have a 7 day waiting period. Claims for incidents during the waiting period are not eligible.\n\nAnnual limits refresh every policy year. Secure Start is best for affordability, Care Plus is the balanced option for most pet parents, and Elite Shield offers the highest protection for pets needing specialist or emergency care.\n\nPolicyholders can use any licensed veterinarian. Petsfolio reviews standard claims in one to three business days if the invoice and medical notes are complete.",
            ],
            [
                'title'     => 'Petsfolio Claims Playbook',
                'file_name' => 'seed-claims-playbook.txt',
                'language'  => 'en',
                'content'   => "To submit a claim, pay the veterinarian first and then upload the itemized invoice, diagnosis, and any lab or discharge notes from the visit. Claims should be filed within 90 days of treatment.\n\nPetsfolio checks the documents for completeness within 24 hours for Care Plus and same day for some emergency Elite Shield claims. If information is missing, the customer receives a clear request for the exact record needed.\n\nApproved claims are reimbursed to the registered bank account. Reimbursement percentages depend on the plan: Secure Start 70 percent, Care Plus 80 percent, and Elite Shield 90 percent.\n\nPetsfolio does not reimburse routine grooming, food, boarding, or elective cosmetic procedures. These are standard exclusions across the plans.",
            ],
            [
                'title'     => 'Petsfolio Pricing and Recommendation Guide',
                'file_name' => 'seed-pricing-guide.txt',
                'language'  => 'en',
                'content'   => "Dog pricing examples: Secure Start Dog costs 28 dollars per month with a 5,000 dollar annual limit. Care Plus Dog costs 46 dollars per month with a 10,000 dollar annual limit. Elite Shield Dog costs 69 dollars per month with a 20,000 dollar annual limit.\n\nCat pricing examples: Secure Start Cat costs 22 dollars per month with a 4,000 dollar annual limit. Care Plus Cat costs 35 dollars per month with an 8,000 dollar annual limit. Elite Shield Cat costs 52 dollars per month with a 15,000 dollar annual limit.\n\nRecommendation guidance: choose Secure Start for budget-sensitive accident cover, Care Plus for balanced accident and illness cover, and Elite Shield when you need the highest reimbursement, faster claims handling, or specialist support.",
            ],
            [
                'title'     => 'Petsfolio हिंदी योजना सारांश',
                'file_name' => 'seed-plan-summary-hi.txt',
                'language'  => 'hi',
                'content'   => "Petsfolio पालतू बीमा में दुर्घटना, बीमारी, डायग्नोस्टिक्स, सर्जरी, प्रिस्क्रिप्शन दवाएं और हॉस्पिटलाइजेशन प्लान के अनुसार कवर होते हैं। रीइम्बर्समेंट डिडक्टिबल के बाद मिलता है। पहले से मौजूद बीमारी कभी कवर नहीं होती।\n\nडॉग प्लान: सिक्योर स्टार्ट डॉग 28 डॉलर प्रति माह, केयर प्लस डॉग 46 डॉलर प्रति माह, एलीट शील्ड डॉग 69 डॉलर प्रति माह है।\n\nकैट प्लान: सिक्योर स्टार्ट कैट 22 डॉलर प्रति माह, केयर प्लस कैट 35 डॉलर प्रति माह, एलीट शील्ड कैट 52 डॉलर प्रति माह है।\n\nकम बजट के लिए सिक्योर स्टार्ट, संतुलित कवर के लिए केयर प्लस, और सबसे अधिक सुरक्षा के लिए एलीट शील्ड चुनें।",
            ],
            [
                'title'     => 'Petsfolio हिंदी क्लेम गाइड',
                'file_name' => 'seed-claims-guide-hi.txt',
                'language'  => 'hi',
                'content'   => "क्लेम जमा करने के लिए पहले वेट को भुगतान करें, फिर आइटमाइज्ड बिल, डायग्नोसिस और मेडिकल नोट्स अपलोड करें। इलाज के 90 दिनों के भीतर क्लेम फाइल करना चाहिए।\n\nसिक्योर स्टार्ट में 70 प्रतिशत, केयर प्लस में 80 प्रतिशत, और एलीट शील्ड में 90 प्रतिशत रीइम्बर्समेंट मिलता है।\n\nगूमिंग, भोजन, बोर्डिंग, कॉस्मेटिक प्रक्रियाएं और पहले से मौजूद बीमारी कवर नहीं होती। यदि दस्तावेज़ पूरे हों तो क्लेम समीक्षा आम तौर पर 1 से 3 कार्यदिवस में पूरी हो जाती है।",
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function extractKeywords(string $text): array
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9\x{0900}-\x{097F}\s]+/u', ' ', $text) ?? '');
        $tokens = preg_split('/\s+/', $normalized) ?: [];
        $stopWords = [
            'the', 'and', 'for', 'with', 'this', 'that', 'from', 'into', 'your', 'have', 'has',
            'are', 'was', 'were', 'will', 'would', 'should', 'within', 'after', 'then', 'than',
            'यह', 'और', 'के', 'में', 'का', 'की', 'को', 'से', 'पर', 'है', 'था', 'थे', 'लिए',
        ];

        $keywords = [];
        foreach ($tokens as $token) {
            if ($token === '' || in_array($token, $stopWords, true) || mb_strlen($token) < 3) {
                continue;
            }

            $keywords[$token] = true;
        }

        return array_slice(array_keys($keywords), 0, 20);
    }
}
