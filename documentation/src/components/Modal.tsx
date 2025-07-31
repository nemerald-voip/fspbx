import React from "react";
import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from "@headlessui/react";
import { XMarkIcon } from "@heroicons/react/24/outline";

type ModalProps = {
  open: boolean;
  onClose: () => void;
  title?: string;
  children: React.ReactNode;
  maxWidth?: string; // e.g. "sm:max-w-lg"
};

const Modal: React.FC<ModalProps> = ({
  open,
  onClose,
  children,
  title,
  maxWidth = "sm:max-w-lg",
}) => {
  return (
    <Dialog open={open} onClose={onClose} className="relative z-50">
      <DialogBackdrop className="fixed inset-0 bg-black/30 backdrop-blur-sm transition-opacity" />
      <div className="fixed inset-0 z-50 w-screen overflow-y-auto">
        <div className="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
          <DialogPanel
            className={`relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-900 px-4 pt-5 pb-4 text-left shadow-xl transition-all ${maxWidth} w-full sm:p-6`}
          >
            <button
              type="button"
              onClick={onClose}
              className="absolute right-3 top-3 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none"
              aria-label="Close"
            >
              <XMarkIcon className="size-6" aria-hidden="true" />
            </button>
            {title && (
              <DialogTitle as="h3" className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                {title}
              </DialogTitle>
            )}
            {children}
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  );
};

export default Modal;
