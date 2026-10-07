<?php

return [
    'environments' => ['studio', 'nature', 'luxury', 'urban', 'home', 'abstract'],
    'video_durations' => [5, 8, 10],

    // How strongly a scene control steers the final image. `short` is the
    // compact chip tag, `hint` is the caption/tooltip explaining the effect.
    'impact_levels' => [
        'high' => [
            'key' => 'high',
            'label' => 'تأثیر عمده',
            'short' => 'عمده',
            'hint' => 'این انتخاب ترکیب‌بندی و پرسپکتیو صحنه را می‌سازد و خروجی را کاملاً عوض می‌کند.',
        ],
        'medium' => [
            'key' => 'medium',
            'label' => 'تأثیر متوسط',
            'short' => 'متوسط',
            'hint' => 'این انتخاب فضا، مواد و پردازش نور صحنه را تغییر می‌دهد بدون اینکه چیدمان اصلی را جابه‌جا کند.',
        ],
        'subtle' => [
            'key' => 'subtle',
            'label' => 'تأثیر جزئی',
            'short' => 'جزئی',
            'hint' => 'این انتخاب جزئیات تزئینی و لهجه‌های صحنه را اضافه می‌کند و کل ترکیب را دست نمی‌زند.',
        ],
    ],

    // Keyed by the studio field name (the radio `name`); every option of a
    // control inherits its control level, so adding a config item never needs
    // an impact decision here.
    'impacts' => [
        'goal' => 'high',
        'style' => 'high',
        'format' => 'high',
        'environment' => 'medium',
        'surface' => 'medium',
        'lighting_setup' => 'medium',
        'camera_angle' => 'high',
        'props' => 'subtle',
        'character_consistency' => 'high',
    ],

    // Redesign 4: one Persian clause per option, keyed by the studio field
    // name exactly like impacts. Every clause stands on its own (the option
    // card shows it under the label) and also fills its slot in
    // summary_template below, so the scene summary and the hover cards can
    // never describe the same choice in two different ways. Keep them short:
    // the summary reads as one sentence.
    'effects' => [
        'goal' => [
            'introduction' => 'معرفی محصول و نمایش هویت بصری آن',
            'sales' => 'تقویت پیام فروش و جذابیت خرید',
            'branding' => 'تأکید بر هویت و نشان برند',
            'promotion' => 'حس فوریت و پیشنهاد ویژه',
            'launch' => 'حس کنجکاوی و تازگی محصول جدید',
            'engagement' => 'درگیرکننده و قابل اشتراک برای مخاطب',
        ],
        'style' => [
            'luxury' => 'حس و حال لوکس با پالت گرم و جزئیات درخشان',
            'minimal' => 'سادگی، فضای خالی و تمرکز کامل روی محصول',
            'cinematic' => 'نور دراماتیک، عمق و حال‌وهوای سینمایی',
            'natural' => 'نور طبیعی، بافت واقعی و بدون اغراق',
            'colorful' => 'پالت رنگارنگ و کنتراست بالا برای جلب توجه',
            'dark' => 'پس‌زمینه تیره و کنتراست بالا برای حس پریمیوم',
            'professional' => 'بی‌طرف، تمیز و مناسب کاتالوگ و وب‌سایت',
            'fashion' => 'ادیتوریال، مدل‌محور و شبیه نشریات مد',
        ],
        'format' => [
            'instagram_post' => 'قاب مربعی ۱:۱ برای پست اینستاگرام',
            'instagram_story' => 'قاب عمودی ۹:۱۶ برای استوری',
            'instagram_reel' => 'قاب عمودی ۹:۱۶ با حرکت دوربین برای ریلز',
            'tiktok' => 'قاب عمودی ۹:۱۶ با حس سریع و جوان برای تیک‌تاک',
        ],
        'environment' => [
            'studio' => 'پس‌زمینه استودیویی خنثی و کنترل‌شده',
            'nature' => 'فضای باز طبیعت با آسمان و نور پراکنده',
            'luxury' => 'دکور داخلی لوکس با متریال گران‌قیمت',
            'urban' => 'صحنه شهری با خطوط معماری و نئون',
            'home' => 'خانه و دکور صمیمی با گرمی و نرمی',
            'abstract' => 'فرم‌های انتزاعی و گرادیان‌های مدرن',
        ],
        'surface' => [
            'default' => 'سکوی استودیویی خنثی و بدون بافت',
            'marble' => 'سکوی سنگ مرمر با بافت سرد و صیقلی',
            'wood' => 'پایه چوب طبیعی با گرما و بافت دانه‌دار',
            'concrete' => 'سکوی بتنی مینیمال با حس صنعتی',
            'water' => 'سطح آب با بازتاب و امواج ملایم',
            'obsidian' => 'آبسیدین سیاه صیقلی با بازتاب آینه‌ای',
            'sand' => 'ماسه کویری با گرما و بافت دانه‌دار',
        ],
        'lighting_setup' => [
            'softbox' => 'نور یکنواخت و نرم سافت‌باکس با سایه ملایم',
            'sunlight' => 'نور مایل و گرم آفتاب با سایه‌های تیزتر',
            'rim' => 'نور لبه‌ای که حاشیه محصول را جدا می‌کند',
            'neon' => 'نور نئون رنگی با کنتراست بالا و حس شب',
        ],
        'camera_angle' => [
            'eye_level' => 'زاویه هم‌سطح و تراز با محصول از روبرو',
            'flat_lay' => 'نمای بالاسری تخت و مستقیم از بالا',
            'hero_shot' => 'نمای پایین حماسی که محصول را بزرگ می‌کند',
            'macro' => 'نمای خیلی نزدیک با عمق میدان کم',
            'isometric' => 'نمای ایزومتریک سه‌چهارم با حجم',
            'side_angle' => 'نمای جانبی برای نشان دادن عمق',
        ],
        'props' => [
            'none' => 'صحنه بدون اکسسوری اضافه و خلوت',
            'botanical' => 'برگ و گیاه تازه گِرد محصول',
            'splash' => 'قطرات و پاشش آب معلق در هوا',
            'smoke' => 'دود و مه ملایم برای عمق فضا',
            'crystals' => 'کریستال‌های شیشه‌ای برای انعکاس نور',
        ],
        'character_consistency' => [
            'dynamic' => 'مدل و چهره‌های متنوع در هر تولید',
            'locked' => 'چهره و هویت مدل ثابت در همه تولیدها',
        ],
    ],

    // The Persian sentence the studio reads back under the canvas. Only the
    // grammar lives here: every {control} slot is filled from effects above
    // (and {product} by the caller), so editing the wording never needs a
    // JavaScript change.
    'summary_template' => '{product} در {environment}، روی {surface}، با {camera_angle} و {lighting_setup} چیده می‌شود؛ {props}، {style}، {goal}، در {format} و با {character_consistency}.',

    'surfaces' => [
        'default' => [
            'key' => 'default',
            'label' => 'استودیویی خنثی',
            'prompt' => '',
            'icon' => '⚪',
        ],
        'marble' => [
            'key' => 'marble',
            'label' => 'سنگ مرمر لوکس',
            'prompt' => 'displayed on a luxury white veined Carrara marble pedestal with soft specular highlights',
            'icon' => '🏛️',
        ],
        'wood' => [
            'key' => 'wood',
            'label' => 'پایه چوب طبیعی',
            'prompt' => 'staged on a solid rustic natural wood slab with rich warm grain texture',
            'icon' => '🪵',
        ],
        'concrete' => [
            'key' => 'concrete',
            'label' => 'سکوی بتنی مینیمال',
            'prompt' => 'positioned on an architectural polished minimal concrete podium',
            'icon' => '🧱',
        ],
        'water' => [
            'key' => 'water',
            'label' => 'سطح آب با امواج',
            'prompt' => 'resting on a clear crystal water surface with gentle ripples and caustic light reflections',
            'icon' => '💧',
        ],
        'obsidian' => [
            'key' => 'obsidian',
            'label' => 'آبسیدین صیقلی',
            'prompt' => 'elevated on a glossy black obsidian mirror surface with sharp glossy ground reflections',
            'icon' => '💎',
        ],
        'sand' => [
            'key' => 'sand',
            'label' => 'ماسه کویر',
            'prompt' => 'nestled on fine warm golden desert sand with subtle wind-blown dunes',
            'icon' => '🏜️',
        ],
    ],

    'props' => [
        'none' => [
            'key' => 'none',
            'label' => 'بدون اکسسوری اضافه',
            'prompt' => '',
            'icon' => '✨',
        ],
        'botanical' => [
            'key' => 'botanical',
            'label' => 'ارگانیک و گیاهی',
            'prompt' => 'accented with lush monstera leaves, olive branches, and delicate pink orchid petals',
            'icon' => '🌿',
        ],
        'splash' => [
            'key' => 'splash',
            'label' => 'پاشش قطرات معلق',
            'prompt' => 'surrounded by dynamic crystal water splashes, microscopic floating droplets, and morning dew',
            'icon' => '💦',
        ],
        'smoke' => [
            'key' => 'smoke',
            'label' => 'دود و مه ملایم',
            'prompt' => 'enveloped in gentle atmospheric ethereal fog and soft cinematic dry-ice smoke wisps',
            'icon' => '🌫️',
        ],
        'crystals' => [
            'key' => 'crystals',
            'label' => 'کریستال‌ها و هندسی',
            'prompt' => 'flanked by floating geometric glass prisms and translucent crystal shards scattering spectrum colors',
            'icon' => '🔮',
        ],
    ],

    // Every entry carries:
    //  - prompt: the locked camera directive, front-loaded into the prompt.
    //  - motion: Veo/video camera movement that stays consistent with the
    //    chosen angle instead of the generic horizontal pan.
    'camera_angles' => [
        'eye_level' => [
            'key' => 'eye_level',
            'label' => 'زاویه روبرو',
            'prompt' => 'strict eye-level horizontal shot, straight-on camera angle with the lens exactly at product height, never looking down or up',
            'motion' => 'smooth lateral dolly pan kept perfectly at eye level',
            'icon' => '👁️',
        ],
        'flat_lay' => [
            'key' => 'flat_lay',
            'label' => 'عکاسی از بالا (Flat-Lay)',
            'prompt' => '90-degree overhead top-down flat-lay shot, camera pointing straight down at the product from directly above',
            'motion' => 'slow overhead glide drifting directly above the flat-lay',
            'icon' => '📐',
        ],
        'hero_shot' => [
            'key' => 'hero_shot',
            'label' => 'زاویه پایین حماسی (Hero)',
            'prompt' => 'dramatic low-angle hero perspective looking upward at the product, camera below eye level making the product tower over the viewer',
            'motion' => 'slow push-in rising from a low angle beneath the product',
            'icon' => '👑',
        ],
        'macro' => [
            'key' => 'macro',
            'label' => 'نمای کلوزآپ/ماکرو',
            'prompt' => 'extreme close-up macro shot with shallow depth of field, camera tight on the product surface revealing texture and craftsmanship',
            'motion' => 'subtle macro focus pull with almost no camera travel',
            'icon' => '🔍',
        ],
        'isometric' => [
            'key' => 'isometric',
            'label' => 'زاویه ۴۵ درجه ایزومتریک',
            'prompt' => '45-degree isometric three-quarter view from above, tilted camera showing the top and the front side of the product at once',
            'motion' => 'gentle orbital arc holding the 45-degree isometric viewpoint',
            'icon' => '🧊',
        ],
        'side_angle' => [
            'key' => 'side_angle',
            'label' => 'زاویه جانبی',
            'prompt' => 'side profile angle shot, camera positioned 90 degrees to the side of the product showing its silhouette edge-on',
            'motion' => 'slow tracking shot sliding along the side profile of the product',
            'icon' => '↔️',
        ],
    ],

    'lighting_setups' => [
        'softbox' => [
            'key' => 'softbox',
            'label' => 'سافت‌باکس استودیویی',
            'prompt' => 'Lighting: balanced high-end dual commercial softbox studio lighting with diffused even illumination',
            'icon' => '💡',
        ],
        'sunlight' => [
            'key' => 'sunlight',
            'label' => 'نور طبیعی پنجره',
            'prompt' => 'Lighting: warm afternoon window sunlight streaming through with soft cinematic architectural shadows',
            'icon' => '☀️',
        ],
        'rim' => [
            'key' => 'rim',
            'label' => 'نور لبه‌ای دراماتیک',
            'prompt' => 'Lighting: dramatic high-contrast edge rim lighting sculpting the product contours against a moody backdrop',
            'icon' => '⚡',
        ],
        'neon' => [
            'key' => 'neon',
            'label' => 'نور نئون سایبرنتیک',
            'prompt' => 'Lighting: futuristic duotone cyber neon backlight with subtle magenta and cyan ambient glow',
            'icon' => '🟣',
        ],
    ],

    // Which character state the studio preselects and the engine falls back to
    // when a request carries none. Must be a key of character_consistencies.
    'character_consistency_default' => 'dynamic',

    'character_consistencies' => [
        'dynamic' => [
            'key' => 'dynamic',
            'label' => 'تنوع و مدل جدید',
            'prompt' => '',
            'icon' => '🎲',
        ],
        'locked' => [
            'key' => 'locked',
            'label' => 'ثابت و بدون تغییر',
            'prompt' => 'strict character consistency, identical facial features, same model identity across generations, preserve facial structure and ethnicity, zero character drift',
            'icon' => '🔒',
        ],
    ],

    // Redesign 8: what may be written inside the frame. `strict` is the tail of
    // every prompt; `permissive` applies only when the brief itself asks for
    // wording (brand tagline, or a text request inside the custom scene
    // details) - otherwise the two would contradict each other. Both are plain
    // sentences without placeholders, so the studio mirror can render them
    // straight from the form markup.
    'text_suppression' => [
        'strict' => 'strictly clean composition, no text, no words, no letters, no typography, no fake labels, no pseudo-writing, no artificial watermark or signage, keep the original product packaging and label artwork exactly as it is',
        'permissive' => 'strictly clean composition, no text, no words, no letters, no typography, no fake labels, no pseudo-writing, no artificial watermark or signage, except the wording the brief explicitly requests, rendered exactly as written and legibly, and nothing else',
        // Whole-word matches (Persian and Latin) inside the custom scene details.
        'request_keywords' => [
            'متن',
            'نوشته',
            'تایپوگرافی',
            'شعار',
            'عنوان',
            'تیتر',
            'فونت',
            'حروف',
            'text',
            'typography',
            'lettering',
            'headline',
            'slogan',
            'font',
            'typeface',
        ],
        // Scene descriptors must never suggest a text-bearing composition.
        'banned_prompt_words' => [
            'poster',
            'typograph',
            'font',
            'typeface',
            'lettering',
            'calligraph',
            'graffiti',
            'billboard',
            'signage',
            'banner',
            'magazine',
            'newspaper',
            'headline',
            'printed text',
            'written text',
        ],
    ],
];
