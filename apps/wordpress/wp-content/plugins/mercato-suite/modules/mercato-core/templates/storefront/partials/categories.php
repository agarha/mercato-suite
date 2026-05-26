<?php /** @var array $data */ /** @var \Closure $esc */ /** @var \Closure $attr */ ?>
<section class="section" id="categories">
  <div class="section-head">
    <div><h2>Browse every service category</h2><p>Tenant-scoped categories and subcategories are loaded from the Gigsii marketplace taxonomy.</p></div>
    <span class="pill">Task-style hierarchy</span>
  </div>
  <div class="category-grid">
    <?php if (empty($data['categories'])): ?>
      <article class="empty-state"><h3>No categories yet</h3><p>Seed tenant categories to show marketplace browse structure.</p></article>
    <?php else: foreach ($data['categories'] as $category): ?>
      <article class="category-card">
        <strong><?= $esc($category['name']) ?></strong>
        <span><?= $esc($category['child_count']) ?> subcategories</span>
      </article>
    <?php endforeach; endif; ?>
  </div>
  <div class="subcategory-cloud">
    <?php if (empty($data['subcategories'])): ?>
      <span>No subcategories yet</span>
    <?php else: foreach ($data['subcategories'] as $sub): ?>
      <span title="<?= $attr($sub['parent_name']) ?>"><?= $esc($sub['name']) ?></span>
    <?php endforeach; endif; ?>
  </div>
</section>
