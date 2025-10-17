<style>
    .letter-container {
        font-family: 'Georgia', 'Times New Roman', serif;
        color: #2c3e50;
        line-height: 1.8;
    }
    .letter-header {
        text-align: center;
        padding: 30px 0;
        border-bottom: 4px double #2c3e50;
        margin-bottom: 30px;
    }
    .letter-header h1 {
        font-size: 32px;
        font-weight: bold;
        color: #1a252f;
        margin: 0;
        letter-spacing: 2px;
        text-transform: uppercase;
    }
    .letter-header h2 {
        font-size: 20px;
        color: #34495e;
        margin: 10px 0 0 0;
        font-weight: normal;
        font-style: italic;
    }
    .letter-info {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 20px;
        border-left: 5px solid #3498db;
        margin: 25px 0;
        border-radius: 0 8px 8px 0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .letter-info p {
        margin: 8px 0;
        font-size: 15px;
    }
    .letter-info strong {
        color: #2c3e50;
        min-width: 120px;
        display: inline-block;
    }
    .letter-salutation {
        margin: 30px 0 20px 0;
        font-size: 16px;
        font-weight: 600;
    }
    .letter-body {
        text-align: justify;
        font-size: 15px;
        margin: 20px 0;
    }
    .executive-summary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px;
        border-radius: 10px;
        margin: 30px 0;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }
    .executive-summary h3 {
        margin: 0 0 15px 0;
        font-size: 22px;
        font-weight: bold;
        border-bottom: 2px solid rgba(255,255,255,0.3);
        padding-bottom: 10px;
    }
    .executive-summary ul {
        list-style: none;
        padding: 0;
        margin: 15px 0;
    }
    .executive-summary li {
        padding: 10px 0;
        font-size: 16px;
        border-bottom: 1px solid rgba(255,255,255,0.2);
    }
    .executive-summary li:last-child {
        border-bottom: none;
    }
    .section-header {
        background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        color: white;
        padding: 15px 20px;
        margin: 40px 0 20px 0;
        border-radius: 8px;
        font-size: 20px;
        font-weight: bold;
        box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);
        display: flex;
        align-items: center;
    }
    .section-header.reviews {
        background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        box-shadow: 0 4px 10px rgba(46, 204, 113, 0.3);
    }
    .section-header i {
        margin-right: 12px;
        font-size: 24px;
    }
    .risk-card {
        margin: 20px 0;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        page-break-inside: avoid;
        transition: transform 0.2s;
    }
    .risk-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.15);
    }
    .risk-card.high {
        background: linear-gradient(135deg, #fff5f5 0%, #ffe5e5 100%);
        border-left: 6px solid #e74c3c;
    }
    .risk-card.middle {
        background: linear-gradient(135deg, #fffbf0 0%, #fff3cd 100%);
        border-left: 6px solid #f39c12;
    }
    .risk-title {
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
    }
    .risk-title.high {
        color: #c0392b;
    }
    .risk-title.middle {
        color: #d68910;
    }
    .risk-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
        margin-left: 10px;
        text-transform: uppercase;
    }
    .risk-badge.high {
        background: #e74c3c;
        color: white;
    }
    .risk-badge.middle {
        background: #f39c12;
        color: white;
    }
    .risk-meta {
        color: #7f8c8d;
        font-size: 14px;
        margin-bottom: 15px;
        padding: 10px;
        background: rgba(255,255,255,0.7);
        border-radius: 5px;
    }
    .risk-meta strong {
        color: #34495e;
    }
    .qa-section {
        margin: 15px 0;
    }
    .qa-item {
        margin: 12px 0;
        padding: 15px;
        background: white;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }
    .qa-question {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
        font-size: 15px;
    }
    .qa-question i {
        color: #3498db;
        margin-right: 8px;
    }
    .qa-answer {
        color: #555;
        margin-left: 24px;
        font-size: 14px;
        line-height: 1.6;
    }
    .qa-answer i {
        color: #2ecc71;
        margin-right: 8px;
    }
    .recommendations {
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        padding: 25px;
        border-radius: 10px;
        margin: 40px 0;
        border: 2px solid #4caf50;
        box-shadow: 0 4px 15px rgba(76, 175, 80, 0.2);
    }
    .recommendations h3 {
        color: #2e7d32;
        margin: 0 0 15px 0;
        font-size: 22px;
        font-weight: bold;
    }
    .recommendations p {
        text-align: justify;
        margin: 12px 0;
        font-size: 15px;
    }
    .recommendations ol {
        margin: 15px 0;
        padding-left: 25px;
    }
    .recommendations li {
        margin: 10px 0;
        font-size: 15px;
        line-height: 1.7;
    }
    .signature-section {
        margin-top: 60px;
        page-break-inside: avoid;
    }
    .signature-section p {
        margin: 8px 0;
        font-size: 15px;
    }
    .signature-line {
        margin-top: 50px;
        border-top: 2px solid #2c3e50;
        width: 250px;
        padding-top: 5px;
    }
    .no-data {
        text-align: center;
        padding: 40px;
        color: #95a5a6;
        font-style: italic;
    }
</style>

<div class="letter-container">
    <!-- Header -->
    <div class="letter-header">
        <h1>Management Letter</h1>
        <h2>Risk Assessment Report</h2>
    </div>
    
    <!-- Letter Info -->
    <div class="letter-info">
        <p><strong>Date:</strong> <?php echo date('F d, Y'); ?></p>
        <p><strong>Company:</strong> <?php echo htmlspecialchars($companyName); ?></p>
        <p><strong>Risk Levels:</strong> <?php echo str_replace(',', ' & ', htmlspecialchars($riskLevels)); ?></p>
    </div>
    
    <!-- Salutation -->
    <p class="letter-salutation">Dear Management,</p>
    
    <!-- Introduction -->
    <div class="letter-body">
        <p>
            We are writing to bring to your attention certain matters that have been identified during our comprehensive review process. 
            The following items have been classified as medium or high risk and require your immediate attention and appropriate action 
            to ensure the continued success and compliance of your organization.
        </p>
    </div>
    
    <!-- Executive Summary -->
    <div class="executive-summary">
        <h3><i class="fa-solid fa-chart-line"></i> Executive Summary</h3>
        <ul>
            <li><strong>Total High Risk Items:</strong> <?php echo $highCount; ?></li>
            <li><strong>Total Middle Risk Items:</strong> <?php echo $middleCount; ?></li>
            <li><strong>Total Items Requiring Attention:</strong> <?php echo ($highCount + $middleCount); ?></li>
        </ul>
    </div>
    
    <!-- Client Queries Section -->
    <?php if (count($queries) > 0): ?>
    <div class="section-header">
        <i class="fa-solid fa-comments"></i>
        <span>Section 1: Client Queries - Risk Items</span>
    </div>
    
    <?php foreach ($queries as $index => $query): 
        $riskClass = strtolower($query['risk_level']);
        $qaPairs = json_decode($query['qa_pairs'], true);
    ?>
    <div class="risk-card <?php echo $riskClass; ?>">
        <div class="risk-title <?php echo $riskClass; ?>">
            <?php echo ($index + 1); ?>. <?php echo htmlspecialchars($query['company_name']); ?>
            <span class="risk-badge <?php echo $riskClass; ?>"><?php echo $query['risk_level']; ?> Risk</span>
        </div>
        <div class="risk-meta">
            <strong>Client:</strong> <?php echo htmlspecialchars($query['client_name']); ?> | 
            <strong>Type:</strong> <?php echo htmlspecialchars($query['query_type']); ?> | 
            <strong>Date:</strong> <?php echo htmlspecialchars($query['query_date']); ?>
        </div>
        
        <?php if ($qaPairs): ?>
        <div class="qa-section">
            <?php foreach ($qaPairs as $qaIndex => $qa): ?>
            <div class="qa-item">
                <div class="qa-question">
                    <i class="fa-solid fa-circle-question"></i>
                    Question <?php echo ($qaIndex + 1); ?>: <?php echo htmlspecialchars($qa['question']); ?>
                </div>
                <div class="qa-answer">
                    <i class="fa-solid fa-circle-check"></i>
                    <?php echo htmlspecialchars($qa['answer']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="no-data">
        <i class="fa-solid fa-inbox fa-3x"></i>
        <p>No client queries found for the selected criteria.</p>
    </div>
    <?php endif; ?>
    
    <!-- Manager Reviews Section -->
    <?php if (count($reviews) > 0): ?>
    <div class="section-header reviews">
        <i class="fa-solid fa-clipboard-check"></i>
        <span>Section 2: Manager Reviews - Risk Items</span>
    </div>
    
    <?php foreach ($reviews as $index => $review): 
        $riskClass = strtolower($review['risk_level']);
        $qaPairs = json_decode($review['qa_pairs'], true);
    ?>
    <div class="risk-card <?php echo $riskClass; ?>">
        <div class="risk-title <?php echo $riskClass; ?>">
            <?php echo ($index + 1); ?>. <?php echo htmlspecialchars($review['company_name']); ?>
            <span class="risk-badge <?php echo $riskClass; ?>"><?php echo $review['risk_level']; ?> Risk</span>
        </div>
        <div class="risk-meta">
            <strong>Manager:</strong> <?php echo htmlspecialchars($review['manager_name']); ?> | 
            <strong>Type:</strong> <?php echo htmlspecialchars($review['review_type']); ?> | 
            <strong>Date:</strong> <?php echo htmlspecialchars($review['review_date']); ?>
        </div>
        
        <?php if ($qaPairs): ?>
        <div class="qa-section">
            <?php foreach ($qaPairs as $qaIndex => $qa): ?>
            <div class="qa-item">
                <div class="qa-question">
                    <i class="fa-solid fa-circle-question"></i>
                    Question <?php echo ($qaIndex + 1); ?>: <?php echo htmlspecialchars($qa['question']); ?>
                </div>
                <div class="qa-answer">
                    <i class="fa-solid fa-circle-check"></i>
                    <?php echo htmlspecialchars($qa['answer']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="no-data">
        <i class="fa-solid fa-inbox fa-3x"></i>
        <p>No manager reviews found for the selected criteria.</p>
    </div>
    <?php endif; ?>
    
    <!-- Recommendations -->
    <div class="recommendations">
        <h3><i class="fa-solid fa-lightbulb"></i> Recommendations and Conclusion</h3>
        <p>
            Based on our comprehensive review, we strongly recommend that management take immediate action to address the high-risk items 
            identified in this letter. The medium-risk items should also be carefully reviewed, and appropriate measures should be 
            implemented to mitigate potential issues and prevent their escalation.
        </p>
        <p><strong>We recommend the following actions:</strong></p>
        <ol>
            <li>Immediate review and response to all high-risk items within the next 48 hours</li>
            <li>Development of comprehensive action plans for medium-risk items within one week</li>
            <li>Regular monitoring and follow-up on all identified risks with weekly status updates</li>
            <li>Implementation of preventive measures and controls to avoid similar issues in the future</li>
            <li>Establishment of a risk management committee to oversee ongoing compliance</li>
        </ol>
        <p>
            Should you require any clarification or additional information regarding the matters outlined in this letter, 
            please do not hesitate to contact us. We remain committed to supporting your organization in addressing these matters effectively.
        </p>
    </div>
    
    <!-- Signature -->
    <div class="signature-section">
        <p>Sincerely,</p>
        <div class="signature-line">
            <p><strong>Management Team</strong></p>
            <p><?php echo date('F d, Y'); ?></p>
        </div>
    </div>
</div>
