<template>
  <span class="on-mark" role="img" aria-label="On">
    <span class="on-toggle" aria-hidden="true"><span class="on-knob"></span></span>
    <span class="on-word" aria-hidden="true">N</span>
  </span>
</template>

<style scoped>
.on-mark {
  display: inline-flex;
  align-items: center;
  gap: .05em;
  color: inherit;
  flex-shrink: 0;
}
.on-toggle {
  display: inline-flex;
  width: 1.18em;
  height: .72em;
  align-items: center;
  justify-content: flex-end;
  padding: .07em;
  border: .025em solid rgba(255, 255, 255, .18);
  border-radius: .36em;
  background: linear-gradient(180deg, #45c65b 0%, #16953b 100%);
  box-shadow: inset 0 .04em .08em rgba(255, 255, 255, .22), 0 .07em .16em rgba(0, 0, 0, .24);
  flex-shrink: 0;
}
.on-knob {
  width: .5em;
  height: .5em;
  flex: 0 0 .5em;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 .03em .08em rgba(0, 0, 0, .28);
}
.on-word {
  display: inline-flex;
  align-items: center;
  height: .72em;
  color: inherit;
  font: inherit;
  font-size: .95em;
  line-height: .72em;
}
</style>
