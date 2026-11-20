declare var wp: any;
declare var jQuery: import('jquery').JQueryStatic;
declare var _: import('underscore');
declare var HTMLHint: import('htmlhint').HTMLHint;
declare var getUserSetting: ( ( name: string, defaultValue?: string ) => string ) | undefined;
declare var setUserSetting: ( ( name: string, value: string | number, remove?: boolean ) => string | false ) | undefined;
