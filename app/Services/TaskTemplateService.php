<?php

namespace App\Services;

class TaskTemplateService
{
    private array $templates = [
        'meeting' => [
            'vi' => [
                'title' => 'Cuộc họp: {subject}',
                'description' => 'Cuộc họp về {subject} với {participants}. Mục tiêu: {objectives}',
                'requirements' => [
                    'Chuẩn bị tài liệu họp',
                    'Gửi lịch họp cho người tham gia',
                    'Kiểm tra phòng họp/link online',
                    'Chuẩn bị agenda chi tiết'
                ],
                'preparation_items' => [
                    'Slide thuyết trình',
                    'Tài liệu tham khảo',
                    'Biên bản họp mẫu',
                    'Danh sách câu hỏi thảo luận'
                ],
                'ai_instructions' => 'Tạo lịch họp chi tiết với đầy đủ thông tin người tham gia, mục tiêu và kết quả mong đợi',
                'metadata' => [
                    'task_type' => 'meeting',
                    'estimated_duration' => '60 minutes',
                    'notification_before' => '15 minutes'
                ]
            ]
        ],
        
        'report' => [
            'vi' => [
                'title' => 'Báo cáo {report_type} - {period}',
                'description' => 'Chuẩn bị báo cáo {report_type} cho kỳ {period}. Nội dung chính: {main_content}',
                'requirements' => [
                    'Thu thập dữ liệu từ các nguồn',
                    'Phân tích và tổng hợp thông tin',
                    'Tạo biểu đồ và hình ảnh minh họa',
                    'Viết tóm tắt điều hành'
                ],
                'preparation_items' => [
                    'Template báo cáo',
                    'Dữ liệu thô',
                    'Công cụ phân tích',
                    'Checklist nội dung báo cáo'
                ],
                'ai_instructions' => 'Tạo báo cáo với cấu trúc rõ ràng, số liệu chính xác và insights có giá trị',
                'metadata' => [
                    'task_type' => 'report',
                    'report_format' => 'detailed',
                    'review_required' => true
                ]
            ]
        ],
        
        'email_campaign' => [
            'vi' => [
                'title' => 'Chiến dịch email: {campaign_name}',
                'description' => 'Triển khai chiến dịch email marketing "{campaign_name}" cho {target_audience}',
                'requirements' => [
                    'Phân khúc danh sách khách hàng',
                    'Thiết kế template email',
                    'Viết nội dung email',
                    'Thiết lập automation flow',
                    'Cài đặt tracking và analytics'
                ],
                'preparation_items' => [
                    'Danh sách email đã phân khúc',
                    'Template email responsive',
                    'Nội dung email A/B testing',
                    'Landing page tương ứng',
                    'UTM parameters cho tracking'
                ],
                'ai_instructions' => 'Tạo chiến dịch email với nội dung cá nhân hóa, call-to-action rõ ràng và theo dõi hiệu quả',
                'metadata' => [
                    'task_type' => 'email_campaign',
                    'campaign_type' => 'marketing',
                    'expected_open_rate' => '25%',
                    'expected_click_rate' => '3%'
                ]
            ]
        ],
        
        'content_creation' => [
            'vi' => [
                'title' => 'Tạo nội dung: {content_type} - {topic}',
                'description' => 'Sản xuất nội dung {content_type} về chủ đề "{topic}" cho {platform}',
                'requirements' => [
                    'Nghiên cứu từ khóa và chủ đề',
                    'Lập dàn ý chi tiết',
                    'Viết nội dung',
                    'Tối ưu SEO',
                    'Thiết kế hình ảnh đi kèm'
                ],
                'preparation_items' => [
                    'Keyword research',
                    'Competitor analysis',
                    'Brand guidelines',
                    'Stock images/graphics',
                    'Publishing calendar'
                ],
                'ai_instructions' => 'Tạo nội dung chất lượng cao, tối ưu SEO và phù hợp với tone of voice của brand',
                'metadata' => [
                    'task_type' => 'content_creation',
                    'content_format' => 'article',
                    'word_count' => '1500',
                    'seo_optimized' => true
                ]
            ]
        ],
        
        'customer_followup' => [
            'vi' => [
                'title' => 'Follow-up khách hàng: {customer_name}',
                'description' => 'Liên hệ follow-up với khách hàng {customer_name} về {purpose}',
                'requirements' => [
                    'Review lịch sử tương tác',
                    'Chuẩn bị talking points',
                    'Gọi điện/gửi email',
                    'Cập nhật CRM',
                    'Lên lịch follow-up tiếp theo'
                ],
                'preparation_items' => [
                    'Customer profile',
                    'Purchase history',
                    'Previous interactions log',
                    'Product/service updates',
                    'Special offers available'
                ],
                'ai_instructions' => 'Thực hiện follow-up chuyên nghiệp, cá nhân hóa và tập trung vào giá trị cho khách hàng',
                'metadata' => [
                    'task_type' => 'customer_followup',
                    'interaction_type' => 'proactive',
                    'priority_level' => 'high'
                ]
            ]
        ],
        
        'project_planning' => [
            'vi' => [
                'title' => 'Lập kế hoạch dự án: {project_name}',
                'description' => 'Xây dựng kế hoạch chi tiết cho dự án "{project_name}" với timeline và resources',
                'requirements' => [
                    'Xác định scope và objectives',
                    'Phân tích stakeholders',
                    'Lập WBS (Work Breakdown Structure)',
                    'Ước tính thời gian và nguồn lực',
                    'Xác định risks và mitigation plans'
                ],
                'preparation_items' => [
                    'Project charter',
                    'Resource availability matrix',
                    'Budget constraints',
                    'Timeline requirements',
                    'Risk register template'
                ],
                'ai_instructions' => 'Tạo kế hoạch dự án toàn diện với milestones rõ ràng, phân bổ nguồn lực hợp lý và quản lý rủi ro',
                'metadata' => [
                    'task_type' => 'project_planning',
                    'methodology' => 'agile',
                    'requires_approval' => true
                ]
            ]
        ],
        
        'data_analysis' => [
            'vi' => [
                'title' => 'Phân tích dữ liệu: {dataset_name}',
                'description' => 'Thực hiện phân tích chi tiết bộ dữ liệu {dataset_name} để {objective}',
                'requirements' => [
                    'Thu thập và làm sạch dữ liệu',
                    'Thực hiện phân tích thống kê',
                    'Tạo visualizations',
                    'Rút ra insights',
                    'Đề xuất actions'
                ],
                'preparation_items' => [
                    'Raw data sources',
                    'Analysis tools/software',
                    'Previous analysis reports',
                    'Stakeholder requirements',
                    'Visualization templates'
                ],
                'ai_instructions' => 'Phân tích dữ liệu sâu với phương pháp khoa học, trình bày insights rõ ràng và actionable',
                'metadata' => [
                    'task_type' => 'data_analysis',
                    'analysis_depth' => 'comprehensive',
                    'tools_required' => ['Excel', 'Python', 'Tableau']
                ]
            ]
        ],
        
        'training_session' => [
            'vi' => [
                'title' => 'Đào tạo: {training_topic}',
                'description' => 'Tổ chức buổi đào tạo về "{training_topic}" cho {audience}',
                'requirements' => [
                    'Chuẩn bị nội dung đào tạo',
                    'Tạo tài liệu học tập',
                    'Setup công cụ/phòng đào tạo',
                    'Chuẩn bị bài tập thực hành',
                    'Tạo form đánh giá'
                ],
                'preparation_items' => [
                    'Training slides',
                    'Handout materials',
                    'Practice exercises',
                    'Assessment forms',
                    'Certificate templates'
                ],
                'ai_instructions' => 'Thiết kế chương trình đào tạo tương tác, dễ hiểu với nhiều ví dụ thực tế',
                'metadata' => [
                    'task_type' => 'training_session',
                    'delivery_method' => 'hybrid',
                    'duration' => '2 hours',
                    'max_participants' => 20
                ]
            ]
        ],
        
        'social_media_post' => [
            'vi' => [
                'title' => 'Post mạng xã hội: {platform} - {topic}',
                'description' => 'Tạo và đăng nội dung về "{topic}" trên {platform}',
                'requirements' => [
                    'Viết caption hấp dẫn',
                    'Thiết kế/chọn hình ảnh',
                    'Thêm hashtags phù hợp',
                    'Lên lịch đăng optimal time',
                    'Setup tracking metrics'
                ],
                'preparation_items' => [
                    'Content calendar',
                    'Brand voice guidelines',
                    'Visual assets',
                    'Hashtag research',
                    'Competitor benchmarks'
                ],
                'ai_instructions' => 'Tạo nội dung viral potential với visual ấn tượng và caption engaging',
                'metadata' => [
                    'task_type' => 'social_media_post',
                    'content_pillars' => ['educational', 'promotional', 'engagement'],
                    'optimal_posting_time' => '19:00-21:00'
                ]
            ]
        ],
        
        'quality_check' => [
            'vi' => [
                'title' => 'Kiểm tra chất lượng: {item_name}',
                'description' => 'Thực hiện QA/QC cho {item_name} theo standards',
                'requirements' => [
                    'Review checklist criteria',
                    'Thực hiện testing',
                    'Document issues found',
                    'Đề xuất improvements',
                    'Sign-off hoặc escalate'
                ],
                'preparation_items' => [
                    'QA checklist',
                    'Testing protocols',
                    'Previous QA reports',
                    'Standards documentation',
                    'Issue tracking system'
                ],
                'ai_instructions' => 'Kiểm tra kỹ lưỡng theo checklist, document đầy đủ và đưa ra recommendations cụ thể',
                'metadata' => [
                    'task_type' => 'quality_check',
                    'qa_level' => 'comprehensive',
                    'compliance_required' => true
                ]
            ]
        ]
    ];

    public function getTemplate(string $type, string $language = 'vi'): ?array
    {
        return $this->templates[$type][$language] ?? null;
    }

    public function getAllTemplates(string $language = 'vi'): array
    {
        $result = [];
        foreach ($this->templates as $key => $template) {
            if (isset($template[$language])) {
                $result[$key] = $template[$language];
            }
        }
        return $result;
    }

    public function generateTaskFromTemplate(string $type, array $variables = [], string $language = 'vi'): ?array
    {
        $template = $this->getTemplate($type, $language);
        if (!$template) {
            return null;
        }

        $task = [
            'title' => $this->replacePlaceholders($template['title'], $variables),
            'description' => $this->replacePlaceholders($template['description'], $variables),
            'requirements' => $template['requirements'],
            'preparation_items' => $template['preparation_items'],
            'event_metadata' => array_merge($template['metadata'], [
                'created_manually' => true,
                'template_used' => $type,
                'ai_instructions' => $template['ai_instructions'],
                'variables_used' => $variables
            ])
        ];

        return $task;
    }

    private function replacePlaceholders(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        return $text;
    }

    public function getTemplateRequirements(): array
    {
        return [
            'meeting' => ['subject', 'participants', 'objectives'],
            'report' => ['report_type', 'period', 'main_content'],
            'email_campaign' => ['campaign_name', 'target_audience'],
            'content_creation' => ['content_type', 'topic', 'platform'],
            'customer_followup' => ['customer_name', 'purpose'],
            'project_planning' => ['project_name'],
            'data_analysis' => ['dataset_name', 'objective'],
            'training_session' => ['training_topic', 'audience'],
            'social_media_post' => ['platform', 'topic'],
            'quality_check' => ['item_name']
        ];
    }
}