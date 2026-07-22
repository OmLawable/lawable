<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/firestore.php';
start_secure_session();

$key = trim($_GET['key'] ?? '');
if ($key !== 'lawable_seed_2024') {
    die('<h2>Access Denied. Add ?key=lawable_seed_2024 to the URL.</h2>');
}

$db = get_firestore();
$courses = $db->query('courses', [], 500);

function generateLessons(string $title): array {
    $t = strtolower($title);
    if (str_contains($t,'constitutional')||str_contains($t,'constitution')) {
        return [
['title'=>'Introduction to Constitutional Law','content'=>"Constitutional law defines the relationship between the state and its citizens.\n\nTopics:\n- The concept and purpose of a constitution\n- Types: written vs unwritten, rigid vs flexible\n- Historical context of constitutional development\n- The Preamble and its significance\n- Fundamental rights guaranteed under the constitution"],
['title'=>'Fundamental Rights and Duties','content'=>"Part III of the Indian Constitution.\n\nTopics:\n- Right to Equality (Articles 14-18)\n- Right to Freedom (Articles 19-22)\n- Right against Exploitation (Articles 23-24)\n- Right to Freedom of Religion (Articles 25-28)\n- Right to Constitutional Remedies (Article 32)\n\nCases: Kesavananda Bharati, Maneka Gandhi, Navtej Singh Johar."],
['title'=>'Directive Principles of State Policy','content'=>"DPSP are guidelines for framing laws for citizen welfare (Part IV, Articles 36-51).\n\nTopics:\n- Socialistic, Gandhian, and Liberal-Intellectual classifications\n- Difference between Fundamental Rights and DPSPs\n- Conflict and judicial interpretation\n\nCases: Minerva Mills v. Union of India, State of Madras v. Champakam Dorairajan."],
['title'=>'Parliament and the Legislative Process','content'=>"India has a bicameral parliament: Lok Sabha and Rajya Sabha.\n\nTopics:\n- Composition and powers of both Houses\n- How a bill becomes an Act\n- Types of bills: Ordinary, Money, Financial, Constitutional Amendment\n- Joint sessions of Parliament\n- Parliamentary privileges and immunities"],
['title'=>'The Judiciary and Constitutional Interpretation','content'=>"The judiciary interprets the constitution and protects citizens rights.\n\nTopics:\n- Structure: Supreme Court, High Courts, District Courts\n- Original, appellate, and advisory jurisdiction\n- Judicial review and its scope\n- Public Interest Litigation (PIL)\n- The doctrine of Basic Structure (Kesavananda Bharati)\n- Constitutional amendments and their limits"],
        ];
    }
    if (str_contains($t,'criminal')||str_contains($t,'crime')||str_contains($t,'ipc')||str_contains($t,'crpc')) {
        return [
['title'=>'Introduction to Criminal Law','content'=>"Criminal law defines crimes and prescribes punishments.\n\nTopics:\n- Nature and purpose of criminal law\n- Sources: IPC 1860, CrPC 1973, Indian Evidence Act 1872\n- New criminal codes: BNS 2023, BNSS 2023, BSA 2023\n- Elements of a crime: actus reus and mens rea\n- Classification: cognizable and non-cognizable offences"],
['title'=>'Offences Against the Human Body','content'=>"Crimes against individuals are the core of criminal law.\n\nTopics under BNS 2023:\n- Murder and culpable homicide\n- Grievous hurt and simple hurt\n- Kidnapping, abduction, and trafficking\n- Sexual offences: rape and sexual assault\n- Dowry death and cruelty against women\n- Child sexual abuse - POCSO Act 2012\n\nCases: Vishakha v. State of Rajasthan, Mukesh v. State NCT of Delhi."],
['title'=>'Property Offences and White Collar Crime','content'=>"Economic and property crimes in modern law.\n\nTopics:\n- Theft, robbery, dacoity, extortion under BNS 2023\n- Cheating and fraud (Section 318, BNS)\n- Criminal misappropriation and breach of trust\n- Money laundering - PMLA 2002\n- Corporate fraud - Section 447, Companies Act 2013\n- Prevention of Corruption Act 1988"],
['title'=>'Criminal Procedure - Investigation and Trial','content'=>"Understanding criminal procedure is essential for practitioners.\n\nBNSS 2023 provisions:\n- Registration of FIR - mandatory under Section 173\n- Police powers of investigation and arrest\n- Bail: bailable and non-bailable offences\n- Chargesheet and cognizance by the court\n- Trial procedures: summons, warrant, sessions trials\n- Judgment and sentencing"],
['title'=>'Evidence Law and Forensics','content'=>"Evidence is the backbone of criminal prosecution.\n\nBSA 2023 provisions:\n- Types of evidence: direct, circumstantial, hearsay\n- Burden of proof\n- Confessions and admissions\n- Electronic evidence (Section 63, BSA)\n- DNA evidence, CCTV footage, and forensic reports\n- Witness protection scheme\n\nCase Study: Arushi Talwar double murder case."],
        ];
    }
    if (str_contains($t,'corporate')||str_contains($t,'company law')||str_contains($t,'business law')) {
        return [
['title'=>'Introduction to Company Law','content'=>"Company law governs formation, operation, and dissolution of companies.\n\nTopics:\n- Types: public, private, OPC, LLP\n- Incorporation process under the Companies Act 2013\n- Memorandum and Articles of Association\n- Certificate of Incorporation\n- Separate legal entity - Salomon v. Salomon & Co. Ltd."],
['title'=>'Directors and Corporate Governance','content'=>"Directors are the primary managing agents of a company.\n\nTopics:\n- Types: Executive, Non-Executive, Independent directors\n- Appointment, qualification, and disqualification\n- Board powers and responsibilities\n- Corporate Governance: transparency, accountability, fairness\n- SEBI listing requirements and audit committees\n- Duties of directors under Section 166, Companies Act 2013"],
['title'=>'Company Meetings and Resolutions','content'=>"Companies make decisions through formal meetings.\n\nTopics:\n- Annual General Meeting (AGM)\n- Extraordinary General Meeting (EGM)\n- Board meetings: quorum, notice, and minutes\n- Ordinary vs Special Resolutions\n- Postal ballot voting\n- NCLT orders regarding meetings\n\nCase Study: LIC v. Escorts Ltd."],
['title'=>'Shares and Share Capital','content'=>"Share capital is the financial foundation of a company.\n\nTopics:\n- Types: authorized, issued, subscribed, paid-up capital\n- Classes of shares: equity and preference\n- Rights attached to shares\n- Transfer and transmission of shares\n- Buy-back under Section 68\n- SEBI regulations on IPO, FPO, rights issues"],
['title'=>'Mergers, Acquisitions, and Winding Up','content'=>"Corporate restructuring is a key area of company law.\n\nTopics:\n- Types of mergers: horizontal, vertical, conglomerate\n- Regulatory approvals: NCLT, CCI, SEBI\n- Due diligence process\n- Scheme of Arrangement under Sections 230-232\n- Voluntary and compulsory winding up\n- Role of the Official Liquidator\n\nCase Study: Tata-Mistry dispute."],
        ];
    }
    if (str_contains($t,'contract')||str_contains($t,'agreement')) {
        return [
['title'=>'Essentials of a Valid Contract','content'=>"The Indian Contract Act, 1872 governs contracts in India.\n\nTopics:\n- Definition of a contract under Section 2(h)\n- Essentials: offer, acceptance, consideration, capacity, free consent, lawful object\n- Void, voidable, and valid contracts\n- Quasi-contracts under Sections 68-72\n\nCase Study: Carlill v. Carbolic Smoke Ball Co."],
['title'=>'Offer, Acceptance, and Consideration','content'=>"Formation of a contract requires clear offer and acceptance.\n\nTopics:\n- Types of offers: general, specific, cross, counter\n- Communication and revocation (Sections 3-6)\n- Rules of valid acceptance\n- Adequacy vs sufficiency of consideration\n- Doctrine of privity of contract and its exceptions"],
['title'=>'Free Consent and Contractual Capacity','content'=>"Consent must be free and parties must be competent to contract.\n\nTopics:\n- Coercion (Section 15)\n- Undue influence (Section 16)\n- Fraud (Section 17)\n- Misrepresentation (Section 18)\n- Mistake (Sections 20-22)\n- Contractual capacity: minors, persons of unsound mind\n\nCase Study: Mohori Bibee v. Dharmodas Ghosh."],
['title'=>'Performance and Discharge of Contracts','content'=>"Contracts must be performed or legally discharged.\n\nTopics:\n- Modes of performance: actual and attempted\n- Discharge by performance, agreement, impossibility, lapse of time\n- Doctrine of frustration (Section 56)\n- Contingent contracts\n- Breach of contract: anticipatory and actual\n\nCase Study: Satyabrata Ghose v. Mugneeram."],
['title'=>'Remedies for Breach of Contract','content'=>"When a contract is breached, the aggrieved party has several remedies.\n\nTopics:\n- Damages: liquidated, unliquidated, nominal, exemplary\n- Rules of remoteness: Hadley v. Baxendale\n- Specific Performance under the Specific Relief Act 1963\n- Injunctions: temporary and permanent\n- Quantum meruit\n- Limitation period under the Limitation Act 1963"],
        ];
    }
    if (str_contains($t,'intellectual property')||str_contains($t,'patent')||str_contains($t,'trademark')||str_contains($t,'copyright')) {
        return [
['title'=>'Introduction to Intellectual Property Law','content'=>"Intellectual Property (IP) law protects creations of the mind.\n\nTopics:\n- Types: Patents, Trademarks, Copyrights, Designs, Trade Secrets\n- International IP framework: TRIPS, WIPO, Paris Convention, Berne Convention\n- Indian IP regulatory bodies: IPO, Copyright Office, CGPDTM\n- Role of IP in innovation and economic development"],
['title'=>'Patent Law','content'=>"Patents protect inventions and innovations.\n\nTopics under Patents Act 1970:\n- Patentable subject matter: novelty, inventive step, industrial applicability\n- Non-patentable subject matter (Section 3)\n- Patent application process\n- Patent term: 20 years\n- Compulsory licensing (Section 84)\n- Patent infringement and remedies\n\nCase Study: Novartis v. Union of India."],
['title'=>'Trademark Law','content'=>"Trademarks protect brand identity in commerce.\n\nTopics under Trade Marks Act 1999:\n- What can be registered: words, logos, shapes, sounds, colors\n- Well-known trademarks and enhanced protection\n- Application, examination, opposition, and registration\n- Trademark infringement vs passing off\n- International trademark: Madrid Protocol\n\nCase Study: Cadila Health Care v. Cadila Pharmaceuticals."],
['title'=>'Copyright Law','content'=>"Copyright protects original literary, artistic, musical, and dramatic works.\n\nCoverage under Copyright Act 1957:\n- Original works eligible for protection\n- Automatic protection - no registration required\n- Economic rights: reproduction, adaptation, distribution\n- Moral rights: attribution and integrity\n- Copyright term: lifetime + 60 years\n- Fair dealing exceptions (Section 52)\n\nCase Study: Eastern Book Company v. D.B. Modak."],
['title'=>'IP Enforcement and Litigation','content'=>"Effective enforcement is key to IP protection.\n\nTopics:\n- Civil remedies: injunction, damages, accounts of profits\n- Criminal remedies under IP statutes\n- Customs enforcement: IP rights recordal\n- Alternative Dispute Resolution in IP disputes\n- IP litigation in Indian courts\n- Border measures and seizure of infringing goods"],
        ];
    }
    if (str_contains($t,'tax')||str_contains($t,'gst')||str_contains($t,'taxation')) {
        return [
['title'=>'Introduction to Taxation in India','content'=>"India has a multi-tier tax system.\n\nTopics:\n- Constitutional basis (Articles 265, 246)\n- Direct taxes: Income Tax, Corporate Tax\n- Indirect taxes: GST, Customs Duty\n- Tax administration: CBDT, CBIC\n- Taxpayer identification: PAN, TAN, GSTIN\n- Overview of tax treaties: DTAA"],
['title'=>'Income Tax - Individuals','content'=>"Key topics under Income Tax Act 1961:\n- Residential status and scope of total income\n- Heads of income: salary, house property, business, capital gains, other sources\n- Deductions under Chapter VI-A: 80C, 80D, 80G, 80E\n- Tax slabs and computation of tax liability\n- Advance tax and TDS provisions\n- New vs Old tax regime comparison"],
['title'=>'GST - Goods and Services Tax','content'=>"GST is Indias comprehensive indirect tax system.\n\nTopics:\n- GST framework: CGST, SGST, IGST, UTGST\n- Registration: threshold limits and compulsory registration\n- Supply: scope, time, and place\n- Input Tax Credit (ITC): eligibility and restrictions\n- GST returns: GSTR-1, GSTR-3B, GSTR-9\n- E-invoicing and E-way bill requirements"],
['title'=>'Corporate Tax and International Taxation','content'=>"Topics:\n- Corporate tax rates: domestic and foreign companies\n- Section 115BAA: reduced tax rate\n- Minimum Alternate Tax (MAT)\n- Transfer Pricing and Arms Length Price\n- General Anti-Avoidance Rules (GAAR)\n- BEPS - OECD framework\n- Country-by-Country Reporting (CbCR)"],
['title'=>'Tax Assessment, Appeals, and Litigation','content'=>"Tax disputes require specialized knowledge.\n\nTopics:\n- Types of assessments: self, summary, scrutiny, best judgment\n- Notices under Income Tax Act: Sections 139, 142, 143, 148\n- Appeals: CIT(A), ITAT, High Court, Supreme Court\n- Alternative dispute resolution: Advance Pricing Agreements\n- Vivad se Vishwas scheme\n- Tax penalties and prosecution provisions"],
        ];
    }
    if (str_contains($t,'labour')||str_contains($t,'labor')||str_contains($t,'employment')||str_contains($t,'industrial')) {
        return [
['title'=>'Overview of Labour Law in India','content'=>"Indian labour law is governed by central and state legislation.\n\nTopics:\n- History and evolution of labour legislation\n- Constitutional provisions: Articles 23, 24, 42, 43, 43A\n- Categories of workers: organized vs unorganized sector\n- The four Labour Codes: Wages, Industrial Relations, Social Security, OSH\n- Role of Labour Department and Labour Courts"],
['title'=>'Wages and Employment Conditions','content'=>"Regulation of wages and working conditions.\n\nTopics:\n- Code on Wages 2019: minimum wage, payment of wages, overtime\n- Factories Act 1948: health, safety, welfare\n- Working hours, weekly off, and leave entitlements\n- Maternity Benefit Act\n- Sexual Harassment of Women at Workplace Act (POSH)"],
['title'=>'Industrial Relations and Trade Unions','content'=>"Industrial relations govern employer-employee collective relationships.\n\nTopics under Industrial Relations Code 2020:\n- Recognition of Trade Unions\n- Collective bargaining and settlement\n- Strike, lockout, and layoff - legal requirements\n- Standing Orders and their certification\n- Grievance Redressal Mechanism\n- Role of Industrial Tribunals and Labour Courts"],
['title'=>'Social Security and Employee Benefits','content'=>"Social security ensures workers have financial protection.\n\nTopics:\n- EPF: Employees Provident Fund Act 1952\n- ESIC: Employees State Insurance Act 1948\n- Payment of Gratuity Act 1972\n- Employees Compensation Act\n- Code on Social Security 2020\n- Social security for gig and platform workers"],
['title'=>'Termination, Retrenchment, and Closure','content'=>"Employment can end in various ways with legal implications.\n\nTopics:\n- Termination for cause: misconduct, domestic enquiry procedure\n- Retrenchment: conditions, compensation calculation\n- Voluntary Retirement Scheme (VRS)\n- Closure of establishments\n- Wrongful termination remedies\n- Non-compete clauses: enforceability in India\n\nCase Study: Air India v. Nargesh Mirza."],
        ];
    }
    if (str_contains($t,'cyber')||str_contains($t,'digital')||str_contains($t,'technology law')||str_contains($t,'it law')) {
        return [
['title'=>'Introduction to Cyber Law','content'=>"Cyber law encompasses all legal issues related to computers and digital communications.\n\nTopics:\n- Evolution of cyber law in India\n- Information Technology Act, 2000 and its 2008 amendment\n- Cyber crimes: hacking, phishing, identity theft, cyberstalking\n- Role of CERT-In\n- Jurisdiction issues in cyberspace"],
['title'=>'Data Protection and Privacy Law','content'=>"Privacy law is rapidly evolving with increasing digitization.\n\nTopics:\n- Right to Privacy: Justice K.S. Puttaswamy v. Union of India\n- Digital Personal Data Protection Act 2023\n- GDPR (EU) and its global implications\n- Data localization requirements\n- Consent, purpose limitation, and data minimization\n- Role of the Data Protection Board"],
['title'=>'E-Commerce and Digital Contracts','content'=>"E-commerce has created new legal frameworks for online transactions.\n\nTopics:\n- Legal validity of electronic contracts\n- Electronic signatures and digital signatures\n- Consumer protection in e-commerce: Rules 2020\n- Platform liability and safe harbor provisions (Section 79, IT Act)\n- IT Rules 2021 on intermediary liability\n- Online dispute resolution"],
['title'=>'Cybercrime Investigation and Evidence','content'=>"How cybercrime is investigated.\n\nTopics:\n- Types of cybercrime and their legal classification\n- Electronic evidence admissibility\n- Chain of custody for digital evidence\n- Powers of investigation under IT Act\n- Mutual legal assistance treaties (MLAT)\n- Dark web investigation challenges"],
['title'=>'Intellectual Property in the Digital Age','content'=>"Digital technology raises complex IP challenges.\n\nTopics:\n- Copyright protection for software and digital content\n- Software patents\n- Domain name disputes and UDRP\n- Trademark infringement online\n- Piracy and DRM (Digital Rights Management)\n- Open source licenses: GPL, MIT, Apache\n- NFTs and blockchain IP considerations\n\nCase Study: Yahoo! v. Akash Arora."],
        ];
    }
    if (str_contains($t,'property')||str_contains($t,'real estate')||str_contains($t,'land')||str_contains($t,'transfer')) {
        return [
['title'=>'Introduction to Property Law','content'=>"Property law governs rights over tangible and intangible property.\n\nTopics:\n- Movable vs immovable, corporeal vs incorporeal property\n- Transfer of Property Act 1882: scope and key definitions\n- Sale, mortgage, lease, exchange, and gift under TPA\n- Doctrine of part performance (Section 53A)\n- Registration Act 1908: compulsory registration\n- Stamp duty on property transactions"],
['title'=>'Sale of Immovable Property','content'=>"The most common form of property transfer is sale.\n\nTopics:\n- Essentials of a valid sale under Section 54, TPA\n- Agreement to sell vs sale deed\n- Title verification: search in land records\n- Due diligence checklist for property purchase\n- RERA (Real Estate Regulation and Development Act 2016)\n- Buyers and sellers rights and obligations"],
['title'=>'Mortgage and Charge','content'=>"Mortgages are common in real estate financing.\n\nTopics under TPA:\n- Types: simple, mortgage by conditional sale, usufructuary, English, anomalous\n- Rights of mortgagor and mortgagee\n- Redemption and foreclosure\n- Priority of mortgages\n- Equitable mortgage by deposit of title deeds\n- SARFAESI Act 2002: banks enforcement powers"],
['title'=>'Lease and Tenancy','content'=>"Lease creates a temporary transfer of possession.\n\nTopics:\n- Definition and essentials of a lease (Section 105, TPA)\n- Rights and duties of lessor and lessee\n- Determination of lease: expiry, forfeiture, surrender\n- Rent Control legislation\n- Leave and License vs Lease\n- Model Tenancy Act 2021"],
['title'=>'Land Acquisition and Urban Development','content'=>"Government acquisition of land for public purpose is heavily regulated.\n\nTopics:\n- Right to Fair Compensation and Transparency in Land Acquisition Act 2013\n- Social Impact Assessment (SIA)\n- Compensation: market value + solatium\n- RERA: developer obligations, escrow account, completion certificates\n- Town and Country Planning legislation"],
        ];
    }
    if (str_contains($t,'environmental')||str_contains($t,'environment')||str_contains($t,'pollution')||str_contains($t,'climate')) {
        return [
['title'=>'Introduction to Environmental Law','content'=>"Environmental law regulates human interaction with the natural environment.\n\nTopics:\n- Constitutional provisions: Articles 48A, 51A(g)\n- Fundamental right to a clean environment under Article 21\n- Key legislation: Environment Protection Act 1986\n- Water (Prevention and Control of Pollution) Act 1974\n- Air (Prevention and Control of Pollution) Act 1981\n- Central Pollution Control Board (CPCB)"],
['title'=>'Environmental Impact Assessment','content'=>"EIA ensures industrial projects do not harm the environment.\n\nTopics:\n- EIA Notification 2006\n- Stages: screening, scoping, assessment, public hearing, approval\n- Clearance categories: A (central) and B (state) projects\n- Role of MoEFCC\n- Environmental clearance conditions and monitoring\n- Coastal Regulation Zone (CRZ) Notification\n\nCase Study: Vedanta Sterlite plant closure."],
['title'=>'Climate Change Law and Policy','content'=>"India has committed to ambitious climate targets.\n\nTopics:\n- Paris Agreement: Indias NDCs\n- Energy Conservation Act 2001 and 2022 amendment\n- National Action Plan on Climate Change (NAPCC)\n- Carbon markets: Indias Carbon Credit Trading Scheme\n- Renewable energy laws\n- Climate litigation globally and in India"],
['title'=>'Pollution Control and Liability','content'=>"Understanding pollution law is essential for practitioners.\n\nTopics:\n- Standards for air and water quality: CPCB notifications\n- Effluent treatment plant requirements\n- Extended Producer Responsibility (EPR)\n- Environmental liability: civil and criminal remedies\n- Absolute liability rule: M.C. Mehta v. Union of India\n- National Green Tribunal (NGT): jurisdiction and procedure"],
['title'=>'Forest Rights and Biodiversity','content'=>"Indias biodiversity and forest laws protect ecosystems and tribal communities.\n\nTopics:\n- Scheduled Tribes and Other Traditional Forest Dwellers Act 2006\n- Community forest rights vs individual rights\n- Biodiversity Act 2002\n- Wildlife crime: CITES compliance\n- Wetland conservation rules 2017\n- CAMPA fund for compensatory afforestation\n\nCase Study: Niyamgiri tribal rights case."],
        ];
    }
    if (str_contains($t,'human rights')||str_contains($t,'fundamental right')) {
        return [
['title'=>'Understanding Human Rights','content'=>"Human rights are universal rights that every person is entitled to.\n\nTopics:\n- Definition, nature, and characteristics of human rights\n- Historical development: Magna Carta to UDHR 1948\n- Generations of rights: civil/political, economic/social, collective\n- International Human Rights Framework: UDHR, ICCPR, ICESCR\n- Regional human rights systems: ECHR, ACHPR\n- Ratification of international treaties by India"],
['title'=>'Constitutional Guarantees in India','content'=>"Indias constitution provides strong human rights protections.\n\nTopics:\n- Fundamental Rights (Part III): Articles 12-35\n- Article 21: Right to life - expanded through judicial interpretation\n- Right to Education (Article 21A) and RTE Act\n- Protections against arbitrary arrest (Articles 22)\n- Anti-discrimination provisions (Articles 15, 16)\n- NHRC - powers and jurisdiction"],
['title'=>'Vulnerable Groups and Special Protections','content'=>"Special legal protections exist for marginalized communities.\n\nTopics:\n- SC/ST (Prevention of Atrocities) Act 1989 and 2016 amendment\n- POCSO Act 2012\n- Juvenile Justice (Care and Protection) Act 2015\n- Rights of Persons with Disabilities Act 2016\n- Transgender Persons (Protection of Rights) Act 2019\n- Protection of refugee rights in India"],
['title'=>'Human Rights Violations and Remedies','content'=>"Understanding how human rights violations are addressed.\n\nTopics:\n- State action doctrine\n- PIL as a tool for human rights enforcement\n- Writ remedies: habeas corpus, mandamus, certiorari\n- NHRC and State Human Rights Commissions\n- UN Treaty Bodies and Special Rapporteurs\n\nCase Study: D.K. Basu v. State of West Bengal."],
['title'=>'Emerging Human Rights Issues','content'=>"Human rights law continuously evolves.\n\nTopics:\n- Right to privacy in the digital age (Puttaswamy case)\n- Environmental rights and climate justice\n- Right to food, water, and housing as constitutional rights\n- LGBT+ rights: decriminalization and ongoing battles\n- Freedom of speech online\n- Human rights in armed conflict: International Humanitarian Law\n- Corporate Human Rights Obligations (UNGPs)"],
        ];
    }
    if (str_contains($t,'banking')||str_contains($t,'finance')||str_contains($t,'financial')||str_contains($t,'investment')) {
        return [
['title'=>'Introduction to Banking and Finance Law','content'=>"Banking law regulates the operations of banks and financial institutions.\n\nTopics:\n- Banking regulation in India: RBI Act 1934, Banking Regulation Act 1949\n- Types of banks: commercial, cooperative, payments, small finance\n- Functions of the Reserve Bank of India\n- Monetary policy tools: repo rate, CRR, SLR\n- Financial sector regulators: SEBI, IRDAI, PFRDA"],
['title'=>'Banking Operations and Products','content'=>"Understanding banking products is essential.\n\nTopics:\n- Types of accounts: current, savings, NRE, NRO\n- Lending products: home loans, personal loans, working capital\n- Priority sector lending\n- Trade finance: LC (Letter of Credit), BG (Bank Guarantee)\n- Payment systems: NEFT, RTGS, IMPS, UPI\n- KYC regulations and AML: PMLA obligations"],
['title'=>'Non-Performing Assets and Recovery','content'=>"NPA management is a critical challenge for Indian banking.\n\nTopics:\n- Definition of NPA and IRAC norms\n- Standard, substandard, doubtful, and loss assets\n- Provisioning requirements\n- Recovery mechanisms: SARFAESI Act 2002, DRT\n- IBC 2016: insolvency resolution process\n- ARC (Asset Reconstruction Companies)\n- RBI Prompt Corrective Action (PCA) framework"],
['title'=>'Securities Law and Capital Markets','content'=>"Securities regulation ensures fair and transparent capital markets.\n\nTopics:\n- Securities market: primary and secondary markets\n- SEBIs regulatory and enforcement powers\n- IPO process and SEBI regulations\n- Listing obligations (LODR Regulations)\n- Insider trading: prohibition and penalties\n- Takeover Code\n- Mutual funds regulation"],
['title'=>'Insurance Law and Fintech Regulation','content'=>"Modern financial law includes insurance and emerging fintech regulation.\n\nTopics:\n- IRDAI and its regulatory role\n- Life insurance vs general insurance\n- Insurance contracts: utmost good faith, insurable interest\n- Motor insurance and third-party liability\n- Health insurance regulations\n- Fintech regulation: P2P lending, payment aggregators\n- Account aggregator framework"],
        ];
    }
    if (str_contains($t,'python')||str_contains($t,'programming')||str_contains($t,'coding')||str_contains($t,'software')) {
        return [
['title'=>'Getting Started with Python','content'=>"Python is one of the most popular programming languages in the world.\n\nTopics:\n- Installing Python and setting up your development environment\n- Running your first Python script\n- Python syntax: indentation, comments, variables\n- Data types: int, float, str, bool\n- Basic input/output operations\n- Arithmetic operators and expressions\n\nPractice: Write a program that takes a users name and age and prints a personalized greeting."],
['title'=>'Control Flow and Functions','content'=>"Control flow determines the order in which code executes.\n\nTopics:\n- Conditional statements: if, elif, else\n- Loops: for loop, while loop\n- Break, continue, and pass statements\n- List comprehensions\n- Defining functions: def, return, parameters, default arguments\n- Lambda functions\n- Recursion: factorial and Fibonacci examples\n\nPractice: Write a function to check whether a number is prime."],
['title'=>'Data Structures in Python','content'=>"Python has powerful built-in data structures.\n\nTopics:\n- Lists: creation, indexing, slicing, methods\n- Tuples: immutability and use cases\n- Dictionaries: key-value pairs, nested dicts\n- Sets: mathematical operations, deduplication\n- Stacks and queues using lists\n- String manipulation: formatting, methods\n\nPractice: Build a phonebook application using dictionaries."],
['title'=>'File Handling and Modules','content'=>"Working with files and modules makes Python versatile.\n\nTopics:\n- Opening, reading, writing, and closing files\n- File modes: r, w, a, rb, wb\n- Context managers: with statement\n- CSV and JSON file handling\n- Importing built-in modules: os, sys, math, datetime\n- Creating your own modules and packages\n\nPractice: Read a CSV file and compute statistics."],
['title'=>'Object-Oriented Programming','content'=>"OOP allows you to model real-world entities in code.\n\nTopics:\n- Classes and objects\n- The __init__ constructor method\n- Instance attributes and methods\n- Encapsulation: private and public attributes\n- Inheritance: parent and child classes\n- Method overriding and super()\n- Polymorphism\n\nPractice: Design a class hierarchy for a library management system."],
        ];
    }
    if (str_contains($t,'web')||str_contains($t,'html')||str_contains($t,'css')||str_contains($t,'javascript')||str_contains($t,'frontend')) {
        return [
['title'=>'HTML Foundations','content'=>"HTML is the structure of every webpage.\n\nTopics:\n- HTML document structure: doctype, html, head, body\n- Semantic elements: header, nav, main, article, section, footer\n- Text elements: headings, paragraphs, strong, em\n- Links and images: anchor tags, img, alt text\n- Lists, tables, and forms\n- HTML5 media: audio, video, canvas\n\nPractice: Build a multi-page personal portfolio in HTML."],
['title'=>'CSS Styling and Layout','content'=>"CSS brings visual design to HTML.\n\nTopics:\n- Selectors: element, class, ID, attribute, pseudo-class\n- Box model: margin, border, padding, content\n- Positioning: static, relative, absolute, fixed, sticky\n- Display: block, inline, flex, grid\n- Flexbox properties\n- CSS Grid\n- Responsive design: media queries, mobile-first approach\n\nPractice: Create a responsive landing page."],
['title'=>'JavaScript Basics','content'=>"JavaScript adds interactivity to websites.\n\nTopics:\n- JS syntax, variables: var, let, const\n- Data types and type coercion\n- Functions: declaration, expression, arrow functions\n- DOM manipulation: querySelector, addEventListener, innerHTML\n- Events: click, input, submit, keydown\n- Arrays and objects\n- JSON: parse and stringify\n\nPractice: Build a to-do list app."],
['title'=>'Advanced JavaScript and APIs','content'=>"Modern JavaScript enables powerful web applications.\n\nTopics:\n- Promises, async/await, and the fetch API\n- Error handling: try/catch/finally\n- REST API calls and JSON responses\n- Local Storage and Session Storage\n- ES6+ features: spread/rest operators, Map, Set\n- Modules: import and export\n- Event delegation and bubbling\n\nPractice: Build a weather app using the OpenWeatherMap API."],
['title'=>'Version Control and Web Deployment','content'=>"Every developer needs to manage code and deploy projects.\n\nTopics:\n- Git fundamentals: init, add, commit, push, pull\n- Branching and merging: Git Flow\n- GitHub: repositories, pull requests, issues\n- Hosting options: GitHub Pages, Netlify, Vercel\n- Domain names and DNS basics\n- Basic performance optimization\n- Web accessibility: WCAG guidelines, ARIA attributes\n\nPractice: Deploy your portfolio to GitHub Pages."],
        ];
    }
    if (str_contains($t,'data')||str_contains($t,'sql')||str_contains($t,'database')||str_contains($t,'analytics')) {
        return [
['title'=>'Introduction to Databases','content'=>"Databases are the backbone of data-driven applications.\n\nTopics:\n- What is a database? DBMS vs RDBMS\n- Popular databases: MySQL, PostgreSQL, MongoDB, SQLite\n- Database design fundamentals: tables, rows, columns\n- Primary keys, foreign keys, and constraints\n- Data types in SQL\n- Entity-Relationship (ER) diagrams\n- Normalization: 1NF, 2NF, 3NF, BCNF\n\nPractice: Design an ER diagram for an e-commerce database."],
['title'=>'SQL Fundamentals','content'=>"SQL (Structured Query Language) is used to interact with relational databases.\n\nTopics:\n- DDL commands: CREATE, ALTER, DROP, TRUNCATE\n- DML commands: INSERT, UPDATE, DELETE\n- DQL commands: SELECT, WHERE, ORDER BY, GROUP BY, HAVING\n- Aggregate functions: COUNT, SUM, AVG, MAX, MIN\n- Joins: INNER, LEFT, RIGHT, FULL OUTER\n- Subqueries\n\nPractice: Write SQL queries on a sample sales database."],
['title'=>'Advanced SQL and Query Optimization','content'=>"Advanced SQL improves performance and analytical capabilities.\n\nTopics:\n- Window functions: ROW_NUMBER, RANK, DENSE_RANK, LAG, LEAD\n- Common Table Expressions (CTEs)\n- Views: creating, updating, dropping\n- Stored procedures and functions\n- Triggers: BEFORE, AFTER\n- Indexes: clustered, non-clustered, composite\n- Query execution plans and EXPLAIN\n\nPractice: Optimize slow queries on a large dataset."],
['title'=>'Data Analysis and Visualization','content'=>"Turning data into insights requires analysis and visualization skills.\n\nTopics:\n- Introduction to data analysis workflow\n- Python for data analysis: pandas, NumPy\n- Data cleaning: handling missing values, duplicates, outliers\n- Exploratory Data Analysis (EDA)\n- Visualization: Matplotlib, Seaborn, Plotly\n- Dashboards: Tableau or Power BI basics\n\nPractice: Analyze a real dataset and create visualizations."],
['title'=>'Big Data and Cloud Databases','content'=>"Modern data engineering deals with massive datasets.\n\nTopics:\n- Introduction to Big Data: the 3Vs (Volume, Velocity, Variety)\n- Hadoop ecosystem: HDFS, MapReduce, Hive\n- Apache Spark fundamentals\n- NoSQL databases: MongoDB, Cassandra, Redis\n- Cloud databases: AWS RDS, Google BigQuery, Azure SQL\n- Data warehousing: Snowflake, Redshift\n- ETL (Extract, Transform, Load) pipelines"],
        ];
    }
    // Generic fallback
    return [
['title'=>'Introduction to the Course','content'=>"Welcome to " . $title . "!\n\nTopics:\n- Understand the scope and objectives of this course\n- Get an overview of the key topics we will cover\n- Learn about recommended resources and study materials\n- How this subject shapes society and professional practice\n- Set learning goals for the duration of the course\n\nThis course is designed to provide practical knowledge bridging theory and real-world application."],
['title'=>'Core Concepts and Principles','content'=>"Every subject is built on foundational principles.\n\nTopics:\n- Historical origins and evolution\n- Governing legislation and regulations\n- Key legal terms and definitions\n- Primary sources in this area\n- Relationship with other areas of law\n- Constitutional foundations"],
['title'=>'Key Legislation and Case Law','content'=>"Statute and precedent are the two pillars of legal knowledge.\n\nTopics:\n- The most important statutes governing this area\n- Landmark Supreme Court and High Court decisions\n- How courts have interpreted and shaped the law\n- Recent legislative amendments and their impact\n- International comparisons where relevant\n- How to research and cite legal authorities"],
['title'=>'Practical Applications and Procedures','content'=>"Legal knowledge has limited value without practical skill.\n\nTopics:\n- How to apply legal principles to real scenarios\n- Procedural requirements and filing formalities\n- Standard documents and drafting conventions\n- Roles of different stakeholders: clients, lawyers, courts, regulators\n- Ethical obligations of practitioners\n\nStudents will work through practical exercises modeled on real professional scenarios."],
['title'=>'Emerging Issues and Future Trends','content'=>"The law is constantly evolving in response to social, economic, and technological change.\n\nTopics:\n- Recent developments and reforms in " . $title . "\n- Impact of technology: AI, blockchain, and digital transformation\n- Global trends and international harmonization\n- Open debates and unsettled questions in the law\n- Career pathways in this field\n- Resources for continuing legal education"],
    ];
}

$seeded = 0; $skipped = 0; $errors = [];
echo '<!DOCTYPE html><html><head><title>Course Content Seeder - Lawable</title>';
echo '<style>body{font-family:monospace;padding:2rem;background:#FCF8F1;color:#0D1117;} .ok{color:#16a34a} .skip{color:#6B7280} .err{color:#DC2626} h2{font-family:serif;color:#C9933A;}</style>';
echo '</head><body><h2>&#127807; Lawable Course Content Seeder</h2><pre>';

foreach ($courses as $c) {
    $id = $c['__id'];
    $title = $c['title'] ?? '(no title)';
    $lessons = $c['lessons'] ?? [];

    if (!empty($lessons)) {
        $skipped++;
        echo "<span class='skip'>SKIP  : {$id}  ({$title}) &mdash; already has " . count($lessons) . " lessons</span>\n";
        continue;
    }

    try {
        $generatedLessons = generateLessons($title);
        $moduleLessons = [];
        foreach ($generatedLessons as $i => $ldata) {
            $moduleLessons[] = [
                'id'              => 'lesson_' . ($i + 1),
                'title'           => $ldata['title'],
                'content'         => $ldata['content'],
                'videoUrl'        => '',
                'documentUrl'     => '',
                'durationMinutes' => 20 + ($i * 5),
                'sortOrder'       => $i + 1,
                'type'            => 'text',
            ];
        }
        $db->update('courses', $id, ['lessons' => $moduleLessons]);
        $seeded++;
        echo "<span class='ok'>SEEDED: {$id}  ({$title}) &mdash; added " . count($moduleLessons) . " modules</span>\n";
    } catch (\Throwable $e) {
        $errors[] = $id . ': ' . $e->getMessage();
        echo "<span class='err'>ERROR : {$id}  ({$title}) &mdash; " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
}

echo "\n<strong>--- Done ---</strong>\n";
echo "Seeded:  {$seeded}\n";
echo "Skipped: {$skipped}\n";
if (!empty($errors)) echo "Errors:  " . count($errors) . "\n";
echo '</pre></body></html>';
