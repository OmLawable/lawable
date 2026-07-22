<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/firestore.php';

start_secure_session();

if (php_sapi_name() !== 'cli') {
    $key = trim($_GET['key'] ?? '');
    if ($key !== 'lawable_seed_2024') {
        die('<h2>Access Denied. Add ?key=lawable_seed_2024 to the URL.</h2>');
    }
}

$db = get_firestore();

$unlockableCourses = [
    [
        'id'          => 'course_lock_ma_mastery',
        'title'       => 'Corporate Mergers & Acquisitions Mastery',
        'category'    => 'Business Compliance',
        'difficulty'  => 'advanced',
        'price'       => 0,
        'isLocked'    => true,
        'unlockCost'  => 500,
        'imageUrl'    => '../assets/images/business_compliance.png',
        'description' => 'Master cross-border M&A transactions, corporate due diligence, tax structuring, and regulatory approvals under SEBI & NCLT.',
        'lessons'     => [
            ['title' => 'M&A Deal Structures & Asset Purchases', 'content' => "Overview of share purchase agreements (SPA), asset sales, slump sales, and business transfers.\n\nKey Topics:\n- Share vs Asset acquisition\n- Due diligence checklist (Legal, Financial, Environmental)\n- Term sheets and exclusivity agreements\n- Condition precedents (CPs) and Condition subsequents (CSs)"],
            ['title' => 'Corporate Valuation & Due Diligence Strategy', 'content' => "Performing comprehensive legal and corporate governance due diligence.\n\nKey Topics:\n- Identifying title defects, litigation risks, and labor liabilities\n- DCF vs EBITDA multiple valuation methods\n- Disclosure schedules and data room administration\n- Material Adverse Change (MAC) clauses"],
            ['title' => 'NCLT Schemes of Arrangement & Restructuring', 'content' => "Statutory process under Sections 230-232 of Companies Act 2013.\n\nKey Topics:\n- Drafting schemes of amalgamation and demerger\n- Shareholder and creditor class meetings\n- Regional Director (RD) & Official Liquidator (OL) reports\n- NCLT final sanction hearing and filing Form INC-28"],
            ['title' => 'SEBI Takeover Code & Competition Law Clearances', 'content' => "Navigating mandatory open offers and anti-trust approvals.\n\nKey Topics:\n- SEBI SAST Regulations 2011 trigger thresholds (25% voting rights)\n- Mandatory open offer process and escrow accounts\n- Competition Commission of India (CCI) merger control notification\n- Combination thresholds under Competition Act 2002"],
            ['title' => 'Cross-Border M&A & Post-Merger Integration', 'content' => "Inbound and outbound investments under FEMA regulations.\n\nKey Topics:\n- RBI FDI policy: automatic vs government approval routes\n- Cross-Border Merger Rules 2018\n- Transfer pricing & stamp duty on share transfers\n- Post-merger operational and cultural integration"],
        ]
    ],
    [
        'id'          => 'course_lock_fintech_reg',
        'title'       => 'Global FinTech & Regulatory Compliance',
        'category'    => 'Business Compliance',
        'difficulty'  => 'intermediate',
        'price'       => 0,
        'isLocked'    => true,
        'unlockCost'  => 500,
        'imageUrl'    => '../assets/images/database_sql.png',
        'description' => 'Navigate RBI regulatory sandboxes, anti-money laundering (PMLA), digital payment gateways, crypto regulations, and financial privacy.',
        'lessons'     => [
            ['title' => 'Digital Banking & RBI Payment Frameworks', 'content' => "Legal frameworks governing UPI, PPIs, and digital lending.\n\nKey Topics:\n- Payment and Settlement Systems Act 2007\n- RBI Digital Lending Guidelines 2022\n- Neo-banking partnerships & FLDG (First Loss Default Guarantee)\n- Tokenization & card storage guidelines"],
            ['title' => 'AML, KYC & FIU-IND Reporting Rules', 'content' => "Preventing money laundering in digital financial services.\n\nKey Topics:\n- Prevention of Money Laundering Act (PMLA) 2002\n- Video-KYC and electronic customer onboarding\n- Suspicious Transaction Reports (STR) to FIU-IND\n- Beneficial ownership verification"],
            ['title' => 'Crypto, Web3 & Virtual Digital Assets (VDA)', 'content' => "Taxation and legal standing of cryptocurrencies and NFTs.\n\nKey Topics:\n- Section 115BBH 30% flat crypto tax & 1% TDS (194S)\n- FIU-IND registration for crypto exchanges\n- Central Bank Digital Currency (CBDC - e-Rupee) framework\n- Global FATF Travel Rule compliance"],
            ['title' => 'Financial Data Privacy & Open Banking APIs', 'content' => "Data governance for Account Aggregators and credit bureaus.\n\nKey Topics:\n- Account Aggregator (AA) ecosystem architecture\n- Consent management under DPDP Act 2023\n- PCI-DSS compliance for payment processors\n- Cross-border financial data localization rules"],
            ['title' => 'FinTech Regulatory Sandboxes & InsurTech', 'content' => "Testing innovative financial technology in controlled environments.\n\nKey Topics:\n- RBI Regulatory Sandbox cohorts (Cross-border payments, MSME lending)\n- GIFT City IFSCA regulatory framework for FinTechs\n- IRDAI Bima Sugam & InsurTech licensing\n- Algorithmic credit scoring liability"],
        ]
    ],
    [
        'id'          => 'course_lock_ai_legaltech',
        'title'       => 'AI Ethics & Legal Tech Architecture',
        'category'    => 'Technology & Computer Science',
        'difficulty'  => 'advanced',
        'price'       => 0,
        'isLocked'    => true,
        'unlockCost'  => 500,
        'imageUrl'    => '../assets/images/web_dev.png',
        'description' => 'Comprehensive analysis of generative AI liability, algorithm bias, copyright in AI training datasets, and automated contracting.',
        'lessons'     => [
            ['title' => 'Generative AI & Copyright Dataset Disputes', 'content' => "Copyright infringement issues in LLM training and output.\n\nKey Topics:\n- Training data scraping vs Fair Use / Fair Dealing\n- Copyrightability of AI-generated output (Human authorship requirement)\n- New York Times v. OpenAI litigation breakdown\n- Watermarking and synthetic data legalities"],
            ['title' => 'Algorithmic Bias, Deepfakes & Tort Liability', 'content' => "Attributing civil and criminal liability for AI errors.\n\nKey Topics:\n- Product liability for autonomous AI systems\n- Deepfake impersonation & IT Act Section 66D/66E\n- EU AI Act risk categories (Unacceptable, High, Limited, Minimal)\n- Algorithmic discrimination in hiring and credit scoring"],
            ['title' => 'Smart Contracts & Blockchain Enforcement', 'content' => "Automated self-executing agreements and dispute resolution.\n\nKey Topics:\n- Smart contracts under Section 10 of Indian Contract Act\n- Cryptographic signatures and BSA Section 61 admissibility\n- Oracles and real-world event verification failure\n- Decentralized Autonomous Organizations (DAOs) legal status"],
            ['title' => 'Automated Legal Document Assembly & RAG', 'content' => "Building scalable legal automation platforms.\n\nKey Topics:\n- Retrieval-Augmented Generation (RAG) for legal research\n- Prompt engineering for contract clause generation\n- Confidentiality & privilege in enterprise AI tools\n- Unauthorized Practice of Law (UPL) regulatory risk"],
            ['title' => 'Global Governance: EU AI Act & India AI Framework', 'content' => "Regulatory compliance frameworks for AI deployment.\n\nKey Topics:\n- EU AI Act mandatory compliance timelines\n- India AI Mission advisory on AI model testing\n- Governance frameworks for frontier foundation models\n- Corporate AI Risk Committees & audit protocols"],
        ]
    ],
    [
        'id'          => 'course_lock_cyber_forensics',
        'title'       => 'Cyber Law, Forensics & Data Protection',
        'category'    => 'Technology & Computer Science',
        'difficulty'  => 'intermediate',
        'price'       => 0,
        'isLocked'    => true,
        'unlockCost'  => 500,
        'imageUrl'    => '../assets/images/dsa_python.png',
        'description' => 'Master the Digital Personal Data Protection (DPDP) Act 2023, cyber Crime investigation, digital evidence (BSA 2023), and cloud security.',
        'lessons'     => [
            ['title' => 'DPDP Act 2023 Core Obligations & Penalties', 'content' => "Comprehensive breakdown of India's privacy law.\n\nKey Topics:\n- Data Fiduciary, Data Principal & Significant Data Fiduciary (SDF)\n- Notice and consent architecture\n- Rights of Data Principals: access, correction, erasure\n- Penalties up to ₹250 Crore for data breaches"],
            ['title' => 'CERT-In Incident Response & Breach Reporting', 'content' => "Mandatory 6-hour cybersecurity breach notifications.\n\nKey Topics:\n- CERT-In Cyber Security Directions 2022\n- Mandatory 6-hour incident reporting window\n- Log retention rules (5 years requirement)\n- Ransomware payment legalities & OFAC sanctions"],
            ['title' => 'Digital Evidence & Forensics under BSA 2023', 'content' => "Admissibility of electronic records in court proceedings.\n\nKey Topics:\n- Section 63 BSA 2023 (formerly Section 65B Evidence Act)\n- Hash value verification & chain of custody\n- Forensic disk imaging (EnCase, FTK Imager)\n- Mobile device forensics & WhatsApp extraction"],
            ['title' => 'Cyber Crimes & IT Act Prosecutions', 'content' => "Investigating and prosecuting digital offenses.\n\nKey Topics:\n- Hacking (Section 66), Identity theft (Section 66C), Phishing\n- Cyber stalking & online harassment\n- Intermediary liability and safe harbor (Section 79)\n- Information Technology (Intermediary Guidelines) Rules 2021"],
            ['title' => 'Cloud Security & Cross-Border Data Transfers', 'content' => "Legal aspects of cloud infrastructure and international transfers.\n\nKey Topics:\n- Blacklisted countries vs whitelisted cross-border transfer rules\n- Cloud Service Provider (CSP) SLAs and liability caps\n- Multi-tenant data segregation & encryption at rest/transit\n- SOC 2 Type II & ISO 27001 audit standards"],
        ]
    ],
    [
        'id'          => 'course_lock_intl_arbitration',
        'title'       => 'International Arbitration & Dispute Resolution',
        'category'    => 'Law & Justice',
        'difficulty'  => 'advanced',
        'price'       => 0,
        'isLocked'    => true,
        'unlockCost'  => 500,
        'imageUrl'    => '../assets/images/constitutional_law.png',
        'description' => 'Draft cross-border arbitration agreements, manage UNCITRAL & SIAC rules, enforce foreign arbitral awards, and master emergency arbitrators.',
        'lessons'     => [
            ['title' => 'Drafting Cross-Border Arbitration Clauses', 'content' => "Creating bulletproof dispute resolution clauses.\n\nKey Topics:\n- Seat vs Venue distinction (Bhatia International v. BALCO)\n- Choice of substantive law, procedural law, and curial law\n- Institutional vs Ad-hoc arbitration (SIAC, LCIA, ICC, MCIA)\n- Multi-tiered dispute resolution clauses (Med-Arb)"],
            ['title' => 'Emergency Arbitrators & Interim Relief', 'content' => "Securing urgent interim protection across jurisdictions.\n\nKey Topics:\n- Section 9 vs Section 17 under Arbitration Act 1996\n- Enforceability of Emergency Arbitrator awards in India (Amazon v. Future Retail)\n- Anti-suit injunctions and freezing orders (Mareva injunctions)\n- Asset preservation protocols"],
            ['title' => 'Arbitral Procedure: Pleadings to Hearing', 'content' => "Managing international arbitration proceedings.\n\nKey Topics:\n- Procedural Order No. 1 and Terms of Reference\n- Redfern Schedule for document production\n- Written witness statements and expert witness cross-examination\n- Virtual hearings and e-briefs"],
            ['title' => 'Enforcement of Foreign Awards (New York Convention)', 'content' => "Executing foreign arbitral awards under Part II of 1996 Act.\n\nKey Topics:\n- New York Convention 1958 reciprocity requirements\n- Grounds for refusal under Section 48 (Public policy defense)\n- Vijay Karia v. Prysmian Cavi e Sistemi judgment\n- Execution procedure against sovereign assets"],
            ['title' => 'Investment Treaty Arbitration (BITs & ICSID)', 'content' => "Representing investors against foreign states.\n\nKey Topics:\n- Bilateral Investment Treaties (BITs) protections: FET, Expropriation\n- Investor-State Dispute Settlement (ISDS) mechanism\n- ICSID Convention framework\n- India's Model BIT 2016 and exhaustion of local remedies"],
        ]
    ],
    [
        'id'          => 'course_lock_supreme_court',
        'title'       => 'Advanced Constitutional & Supreme Court Advocacy',
        'category'    => 'Law & Justice',
        'difficulty'  => 'advanced',
        'price'       => 0,
        'isLocked'    => true,
        'unlockCost'  => 500,
        'imageUrl'    => '../assets/images/constitutional_law.png',
        'description' => 'Master Special Leave Petitions (SLP under Art 136), Public Interest Litigation (PIL) strategy, and senior counsel oral advocacy skills.',
        'lessons'     => [
            ['title' => 'Drafting Special Leave Petitions (SLP - Article 136)', 'content' => "Pleadings for Supreme Court discretionary jurisdiction.\n\nKey Topics:\n- Framing Question of Law vs Question of Fact\n- Grounds for SLP (Civil & Criminal)\n- Synopsis and list of dates formatting\n- Interlocutory applications (Stay, Bail, Exemption)"],
            ['title' => 'Writ Jurisdiction (Article 32 & Article 226)', 'content' => "Enforcing fundamental rights in constitutional courts.\n\nKey Topics:\n- Five high writs: Habeas Corpus, Mandamus, Certiorari, Prohibition, Quo Warranto\n- Locus standi expansion and PIL jurisprudence\n- Interim relief against executive action\n- Costs and exemplary compensation"],
            ['title' => 'Supreme Court Practice & Procedure Rules', 'content' => "Navigating Supreme Court Rules 2013.\n\nKey Topics:\n- Advocate-on-Record (AoR) system and responsibilities\n- Listing before Chamber Judge, Registrar, and Bench\n- Curative petitions (Rupa Ashok Hurra v. Ashok Hurra)\n- Review petitions under Article 137"],
            ['title' => 'Oral Advocacy & Bench Interaction', 'content' => "Mastering oral arguments before Supreme Court Benches.\n\nKey Topics:\n- 5-minute opening argument strategy\n- Managing bench queries and Judicial pushback\n- Preparation of Convenience Compilations and Case Law Digests\n- Demeanor, tone, and court etiquette"],
            ['title' => 'Constitution Bench Reference & Precedent', 'content' => "Engaging with multi-judge bench jurisprudence.\n\nKey Topics:\n- Article 145(3) Constitution Benches (5, 7, 9 judges)\n- Doctrine of Stare Decisis and overruling precedent\n- Referral orders when benches disagree\n- Prospective overruling doctrine"],
        ]
    ],
    [
        'id'          => 'course_lock_trial_tactics',
        'title'       => 'High-Stakes Legal Negotiation & Trial Tactics',
        'category'    => 'Personal Development',
        'difficulty'  => 'intermediate',
        'price'       => 0,
        'isLocked'    => true,
        'unlockCost'  => 500,
        'imageUrl'    => '../assets/images/personal_development.png',
        'description' => 'Develop persuasive courtroom presence, witness cross-examination strategies, Harvard negotiation frameworks, and client counseling.',
        'lessons'     => [
            ['title' => 'Harvard Negotiation Framework (BATNA & ZOPA)', 'content' => "Principled negotiation for dispute settlement.\n\nKey Topics:\n- Separating people from the problem\n- BATNA (Best Alternative to a Negotiated Agreement)\n- ZOPA (Zone of Possible Agreement) calculation\n- Creating value vs claiming value in commercial disputes"],
            ['title' => 'Client Counseling & Fact Extraction Tactics', 'content' => "Conducting initial client interviews effectively.\n\nKey Topics:\n- Active listening techniques and open-ended questioning\n- Managing client expectations and risk assessment\n- Fee structure negotiation & engagement letters\n- Confidentiality and legal professional privilege"],
            ['title' => 'Art of Cross-Examination in Trial Courts', 'content' => "Impeaching witness credibility during trial.\n\nKey Topics:\n- The 10 Commandments of Cross-Examination (Wellman)\n- Leading questions and control over hostile witnesses\n- Impeachment using prior inconsistent statements (Section 148 BSA)\n- Expert witness cross-examination (Medical, Forensic, Handwriting)"],
            ['title' => 'Opening Statements & Closing Arguments', 'content' => "Persuasive storytelling for trial judges.\n\nKey Topics:\n- Framing the case narrative and emotional core\n- Structure of an effective closing argument\n- Connecting evidence to burden of proof\n- Avoiding improper arguments and objections"],
            ['title' => 'Courtroom Demeanor & De-escalation Skills', 'content' => "Maintaining poise under intense judicial pressure.\n\nKey Topics:\n- Controlling body language and vocal tone\n- Handling aggressive opposing counsel\n- Judicial psychology and adapting to judge preferences\n- Stress management for trial attorneys"],
        ]
    ],
    [
        'id'          => 'course_lock_exec_leadership',
        'title'       => 'Executive Leadership for Modern Lawyers',
        'category'    => 'Personal Development',
        'difficulty'  => 'intermediate',
        'price'       => 0,
        'isLocked'    => true,
        'unlockCost'  => 500,
        'imageUrl'    => '../assets/images/personal_development.png',
        'description' => 'Build and scale modern law practice, master law firm profitability metrics, client pitch strategy, and executive emotional intelligence.',
        'lessons'     => [
            ['title' => 'Law Practice Business Models & Pricing Strategy', 'content' => "Building a profitable legal practice.\n\nKey Topics:\n- Billable hours vs Alternative Fee Arrangements (AFAs)\n- Retainer agreements and success-fee structures\n- Practice economics: Realization rate & Utilization rate\n- Modern law firm equity partnership structures"],
            ['title' => 'Client Acquisition & Legal Brand Positioning', 'content' => "Ethical business development for lawyers.\n\nKey Topics:\n- Bar Council of India advertising restrictions compliance\n- Thought leadership: publishing, speaking, and LinkedIn branding\n- Pitch deck preparation for corporate clients\n- Building strategic referral networks"],
            ['title' => 'Talent Management & Law Firm Operations', 'content' => "Leading legal teams and managing associate turnover.\n\nKey Topics:\n- Recruiting, training, and retaining top legal talent\n- Performance review frameworks and associate career tracks\n- Law firm technology stack (KMS, Billing, CRM)\n- Diversity, Equity, and Inclusion (DEI) initiatives"],
            ['title' => 'Executive Emotional Intelligence & Resilience', 'content' => "Navigating high-stress legal careers without burnout.\n\nKey Topics:\n- EI dimensions: self-awareness, empathy, social skills\n- Managing high-stakes crisis situations\n- Work-life integration & mental health protocols\n- Executive presence in boardrooms and courtrooms"],
            ['title' => 'Scaling & Innovation in Legal Services', 'content' => "Future-proofing legal practice in an automated world.\n\nKey Topics:\n- Productizing legal services (Standardized packages)\n- Alternative Legal Service Providers (ALSPs)\n- Managing international firm alliances and networks\n- Strategic 5-year practice growth planning"],
        ]
    ]
];

$count = 0;
foreach ($unlockableCourses as $c) {
    $courseId = $c['id'];
    $rawLessons = $c['lessons'];
    
    $embeddedLessons = [];
    foreach ($rawLessons as $idx => $les) {
        $lessonId = $courseId . '_les_' . ($idx + 1);
        $lesDoc = [
            'id'              => $lessonId,
            'courseId'        => $courseId,
            'title'           => $les['title'],
            'content'         => $les['content'],
            'durationMinutes' => 45,
            'sortOrder'       => $idx + 1,
            'createdAt'       => date('c')
        ];
        $db->set('lessons', $lesDoc, $lessonId);
        $embeddedLessons[] = $lesDoc;
    }

    $c['lessons'] = $embeddedLessons;
    $c['status']  = 'published';
    $db->set('courses', $c, $courseId);
    $count++;
}

echo "<h2>Successfully seeded {$count} unlockable courses with 5 modules each in Firestore!</h2>";
