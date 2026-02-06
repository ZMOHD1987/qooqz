<?php
// htdocs/api/helpers/ai.php
// AI Integration for recommendations, analytics, chatbot

class AIHelper {
    private static ?PDO $pdo = null;
    private static string $openaiKey = '';
    
    public static function setPDO(PDO $pdo) {
        self::$pdo = $pdo;
    }
    
    public static function setOpenAIKey($key) {
        self::$openaiKey = $key;
    }
    
    // توصيات المنتجات بناءً على سلوك المستخدم
    public static function getProductRecommendations($userId, $limit = 5) {
        if (!self::$pdo) return [];
        
        $stmt = self::$pdo->prepare("
            SELECT p.id, p.name, COUNT(uv.product_id) as views
            FROM user_views uv
            JOIN products p ON uv.product_id = p.id
            WHERE uv.user_id = ?
            GROUP BY p.id
            ORDER BY views DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // تحليل المبيعات باستخدام AI (مثال بسيط)
    public static function analyzeSalesTrend() {
        if (!self::$pdo) return [];
        
        $stmt = self::$pdo->query("
            SELECT DATE(created_at) as date, COUNT(*) as sales
            FROM orders
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Chatbot بسيط باستخدام OpenAI
    public static function chatbotResponse($userMessage) {
        if (empty(self::$openaiKey)) return "AI not configured";
        
        $url = 'https://api.openai.com/v1/chat/completions';
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [['role' => 'user', 'content' => $userMessage]],
            'max_tokens' => 100
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . self::$openaiKey,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'] ?? "Error";
    }
}