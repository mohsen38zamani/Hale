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
    ],

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
];
