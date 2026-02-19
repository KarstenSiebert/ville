<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'accepted' => 'فیلد :attribute باید پذیرفته شود.',
    'accepted_if' => 'فیلد :attribute زمانی که :other برابر با :value است باید پذیرفته شود.',
    'active_url' => 'فیلد :attribute باید یک نشانی اینترنتی معتبر باشد.',
    'after' => 'فیلد :attribute باید تاریخی بعد از :date باشد.',
    'after_or_equal' => 'فیلد :attribute باید تاریخی بعد از یا برابر با :date باشد.',
    'alpha' => 'فیلد :attribute فقط باید شامل حروف باشد.',
    'alpha_dash' => 'فیلد :attribute فقط می‌تواند شامل حروف، اعداد، خط تیره و زیرخط باشد.',
    'alpha_num' => 'فیلد :attribute فقط باید شامل حروف و اعداد باشد.',
    'any_of' => 'فیلد :attribute نامعتبر است.',
    'array' => 'فیلد :attribute باید یک آرایه باشد.',
    'ascii' => 'فیلد :attribute فقط می‌تواند شامل نویسه‌ها و نمادهای تک‌بایتی باشد.',
    'before' => 'فیلد :attribute باید تاریخی قبل از :date باشد.',
    'before_or_equal' => 'فیلد :attribute باید تاریخی قبل از یا برابر با :date باشد.',

    'between' => [
        'array' => 'فیلد :attribute باید بین :min و :max آیتم داشته باشد.',
        'file' => 'فیلد :attribute باید بین :min و :max کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید بین :min و :max باشد.',
        'string' => 'فیلد :attribute باید بین :min و :max نویسه باشد.',
    ],

    'boolean' => 'فیلد :attribute باید درست یا نادرست باشد.',
    'can' => 'فیلد :attribute شامل مقدار غیرمجاز است.',
    'confirmed' => 'تأیید فیلد :attribute مطابقت ندارد.',
    'contains' => 'فیلد :attribute فاقد مقدار الزامی است.',
    'current_password' => 'رمز عبور نادرست است.',
    'date' => 'فیلد :attribute باید یک تاریخ معتبر باشد.',
    'date_equals' => 'فیلد :attribute باید تاریخی برابر با :date باشد.',
    'date_format' => 'فیلد :attribute باید با قالب :format مطابقت داشته باشد.',
    'decimal' => 'فیلد :attribute باید دارای :decimal رقم اعشار باشد.',
    'declined' => 'فیلد :attribute باید رد شود.',
    'declined_if' => 'فیلد :attribute زمانی که :other برابر با :value است باید رد شود.',
    'different' => 'فیلد :attribute و :other باید متفاوت باشند.',
    'digits' => 'فیلد :attribute باید :digits رقم باشد.',
    'digits_between' => 'فیلد :attribute باید بین :min و :max رقم باشد.',
    'dimensions' => 'ابعاد تصویر فیلد :attribute نامعتبر است.',
    'distinct' => 'فیلد :attribute دارای مقدار تکراری است.',
    'doesnt_contain' => 'فیلد :attribute نباید شامل مقادیر زیر باشد: :values.',
    'doesnt_end_with' => 'فیلد :attribute نباید با مقادیر زیر پایان یابد: :values.',
    'doesnt_start_with' => 'فیلد :attribute نباید با مقادیر زیر شروع شود: :values.',
    'email' => 'فیلد :attribute باید یک آدرس ایمیل معتبر باشد.',
    'encoding' => 'فیلد :attribute باید با :encoding رمزگذاری شده باشد.',
    'ends_with' => 'فیلد :attribute باید با یکی از مقادیر زیر پایان یابد: :values.',
    'enum' => 'مقدار انتخاب‌شده برای :attribute نامعتبر است.',
    'exists' => 'مقدار انتخاب‌شده برای :attribute نامعتبر است.',
    'extensions' => 'فیلد :attribute باید یکی از پسوندهای زیر را داشته باشد: :values.',
    'file' => 'فیلد :attribute باید یک فایل باشد.',
    'filled' => 'فیلد :attribute باید دارای مقدار باشد.',

    'gt' => [
        'array' => 'فیلد :attribute باید بیش از :value آیتم داشته باشد.',
        'file' => 'فیلد :attribute باید بزرگ‌تر از :value کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید بزرگ‌تر از :value باشد.',
        'string' => 'فیلد :attribute باید بیشتر از :value نویسه باشد.',
    ],

    'gte' => [
        'array' => 'فیلد :attribute باید :value آیتم یا بیشتر داشته باشد.',
        'file' => 'فیلد :attribute باید بزرگ‌تر یا مساوی :value کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید بزرگ‌تر یا مساوی :value باشد.',
        'string' => 'فیلد :attribute باید بزرگ‌تر یا مساوی :value نویسه باشد.',
    ],

    'hex_color' => 'فیلد :attribute باید یک رنگ هگزادسیمال معتبر باشد.',
    'image' => 'فیلد :attribute باید یک تصویر باشد.',
    'in' => 'مقدار انتخاب‌شده برای :attribute نامعتبر است.',
    'in_array' => 'فیلد :attribute باید در :other وجود داشته باشد.',
    'in_array_keys' => 'فیلد :attribute باید حداقل یکی از کلیدهای زیر را داشته باشد: :values.',
    'integer' => 'فیلد :attribute باید عدد صحیح باشد.',
    'ip' => 'فیلد :attribute باید یک آدرس IP معتبر باشد.',
    'ipv4' => 'فیلد :attribute باید یک آدرس IPv4 معتبر باشد.',
    'ipv6' => 'فیلد :attribute باید یک آدرس IPv6 معتبر باشد.',
    'json' => 'فیلد :attribute باید یک رشته JSON معتبر باشد.',
    'list' => 'فیلد :attribute باید یک لیست باشد.',
    'lowercase' => 'فیلد :attribute باید با حروف کوچک باشد.',

    'lt' => [
        'array' => 'فیلد :attribute باید کمتر از :value آیتم داشته باشد.',
        'file' => 'فیلد :attribute باید کمتر از :value کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید کمتر از :value باشد.',
        'string' => 'فیلد :attribute باید کمتر از :value نویسه باشد.',
    ],

    'lte' => [
        'array' => 'فیلد :attribute نباید بیش از :value آیتم داشته باشد.',
        'file' => 'فیلد :attribute باید کمتر یا مساوی :value کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید کمتر یا مساوی :value باشد.',
        'string' => 'فیلد :attribute باید کمتر یا مساوی :value نویسه باشد.',
    ],

    'mac_address' => 'فیلد :attribute باید یک آدرس MAC معتبر باشد.',

    'max' => [
        'array' => 'فیلد :attribute نباید بیش از :max آیتم داشته باشد.',
        'file' => 'فیلد :attribute نباید بیشتر از :max کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute نباید بیشتر از :max باشد.',
        'string' => 'فیلد :attribute نباید بیشتر از :max نویسه باشد.',
    ],

    'max_digits' => 'فیلد :attribute نباید بیش از :max رقم داشته باشد.',
    'mimes' => 'فیلد :attribute باید فایلی از نوع: :values باشد.',
    'mimetypes' => 'فیلد :attribute باید فایلی از نوع: :values باشد.',

    'min' => [
        'array' => 'فیلد :attribute باید حداقل :min آیتم داشته باشد.',
        'file' => 'فیلد :attribute باید حداقل :min کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید حداقل :min باشد.',
        'string' => 'فیلد :attribute باید حداقل :min نویسه باشد.',
    ],

    'min_digits' => 'فیلد :attribute باید حداقل :min رقم داشته باشد.',
    'missing' => 'فیلد :attribute نباید وجود داشته باشد.',
    'missing_if' => 'فیلد :attribute زمانی که :other برابر با :value است نباید وجود داشته باشد.',
    'missing_unless' => 'فیلد :attribute نباید وجود داشته باشد مگر اینکه :other برابر با :value باشد.',
    'missing_with' => 'فیلد :attribute زمانی که :values وجود دارد نباید وجود داشته باشد.',
    'missing_with_all' => 'فیلد :attribute زمانی که همه :values وجود دارند نباید وجود داشته باشد.',
    'multiple_of' => 'فیلد :attribute باید مضرب :value باشد.',
    'not_in' => 'مقدار انتخاب‌شده برای :attribute نامعتبر است.',
    'not_regex' => 'قالب فیلد :attribute نامعتبر است.',
    'numeric' => 'فیلد :attribute باید عددی باشد.',

    'password' => [
        'letters' => 'فیلد :attribute باید حداقل شامل یک حرف باشد.',
        'mixed' => 'فیلد :attribute باید حداقل شامل یک حرف بزرگ و یک حرف کوچک باشد.',
        'numbers' => 'فیلد :attribute باید حداقل شامل یک عدد باشد.',
        'symbols' => 'فیلد :attribute باید حداقل شامل یک نماد باشد.',
        'uncompromised' => ':attribute واردشده در نشت اطلاعات مشاهده شده است. لطفاً :attribute دیگری انتخاب کنید.',
    ],

    'present' => 'فیلد :attribute باید وجود داشته باشد.',
    'present_if' => 'فیلد :attribute زمانی که :other برابر با :value است باید وجود داشته باشد.',
    'present_unless' => 'فیلد :attribute باید وجود داشته باشد مگر اینکه :other برابر با :value باشد.',
    'present_with' => 'فیلد :attribute زمانی که :values وجود دارد باید وجود داشته باشد.',
    'present_with_all' => 'فیلد :attribute زمانی که همه :values وجود دارند باید وجود داشته باشد.',
    'prohibited' => 'فیلد :attribute مجاز نیست.',
    'prohibited_if' => 'فیلد :attribute زمانی که :other برابر با :value است مجاز نیست.',
    'prohibited_if_accepted' => 'فیلد :attribute زمانی که :other پذیرفته شده باشد مجاز نیست.',
    'prohibited_if_declined' => 'فیلد :attribute زمانی که :other رد شده باشد مجاز نیست.',
    'prohibited_unless' => 'فیلد :attribute مجاز نیست مگر اینکه :other در :values باشد.',
    'prohibits' => 'فیلد :attribute مانع وجود داشتن :other می‌شود.',
    'regex' => 'قالب فیلد :attribute نامعتبر است.',
    'required' => 'فیلد :attribute الزامی است.',
    'required_array_keys' => 'فیلد :attribute باید شامل ورودی‌هایی برای :values باشد.',
    'required_if' => 'فیلد :attribute زمانی که :other برابر با :value است الزامی است.',
    'required_if_accepted' => 'فیلد :attribute زمانی که :other پذیرفته شده باشد الزامی است.',
    'required_if_declined' => 'فیلد :attribute زمانی که :other رد شده باشد الزامی است.',
    'required_unless' => 'فیلد :attribute الزامی است مگر اینکه :other در :values باشد.',
    'required_with' => 'فیلد :attribute زمانی که :values وجود دارد الزامی است.',
    'required_with_all' => 'فیلد :attribute زمانی که همه :values وجود دارند الزامی است.',
    'required_without' => 'فیلد :attribute زمانی که :values وجود ندارد الزامی است.',
    'required_without_all' => 'فیلد :attribute زمانی که هیچ‌یک از :values وجود ندارند الزامی است.',
    'same' => 'فیلد :attribute باید با :other مطابقت داشته باشد.',

    'size' => [
        'array' => 'فیلد :attribute باید شامل :size آیتم باشد.',
        'file' => 'فیلد :attribute باید :size کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید :size باشد.',
        'string' => 'فیلد :attribute باید :size نویسه باشد.',
    ],

    'starts_with' => 'فیلد :attribute باید با یکی از مقادیر زیر شروع شود: :values.',
    'string' => 'فیلد :attribute باید یک رشته باشد.',
    'timezone' => 'فیلد :attribute باید یک منطقه زمانی معتبر باشد.',
    'unique' => ':attribute قبلاً استفاده شده است.',
    'uploaded' => 'بارگذاری فیلد :attribute ناموفق بود.',
    'uppercase' => 'فیلد :attribute باید با حروف بزرگ باشد.',
    'url' => 'فیلد :attribute باید یک نشانی اینترنتی معتبر باشد.',
    'ulid' => 'فیلد :attribute باید یک ULID معتبر باشد.',
    'uuid' => 'فیلد :attribute باید یک UUID معتبر باشد.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'پیام سفارشی',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [],

];
