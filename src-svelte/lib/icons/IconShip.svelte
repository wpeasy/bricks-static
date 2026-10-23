<script lang="ts">
  /**
   * The ShipStatic mark: a two-masted sailing ship in line art, drawn in
   * `currentColor`. Same geometry as the admin menu icon
   * (`Admin\Menu::MENU_ICON_SVG`, masked there so WordPress can recolour it)
   * and a simplified cut of `.wordpress-org/icon.svg` — keep them in step.
   */
  let { size = 24, class: className = '' }: { size?: number; class?: string } = $props();
</script>

<svg
  class={className}
  width={size}
  height={size}
  viewBox="18 48 128 128"
  fill="none"
  stroke="currentColor"
  stroke-width="10"
  stroke-linecap="round"
  stroke-linejoin="round"
  aria-hidden="true"
  focusable="false"
>
  <path d="M26 140 H128 L114 166 Q78 174 40 166 Z" />
  <path d="M62 140 V126 M62 72 V56 M104 140 V126 M104 86 V76" />
  <path d="M40 72 H84 Q96 99 84 126 H40 Q50 99 40 72 Z" />
  <path d="M90 86 H118 Q127 106 118 126 H90 Q97 106 90 86 Z" />
</svg>
