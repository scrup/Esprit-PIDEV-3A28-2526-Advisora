import sys
from easygoogletranslate import EasyGoogleTranslate

def main():
    if len(sys.argv) < 4:
        print("Usage: python translate.py <source> <target> <text>")
        sys.exit(1)

    source = sys.argv[1]
    target = sys.argv[2]
    text = sys.argv[3]

    translator = EasyGoogleTranslate(
        source_language=source,
        target_language=target,
        timeout=10
    )

    result = translator.translate(text)
    print(result)

if __name__ == "__main__":
    main()